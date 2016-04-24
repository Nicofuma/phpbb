<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\template;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\exception\runtime_exception;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Determines the appropriate style.
 */
class style_helper
{
	/** @var driver_interface */
	private $db;

	/** @var config */
	private $config;

	/** @var AuthorizationCheckerInterface */
	private $authorization_checker;

	/** @var int */
	private $requested_style;

	/** @var int */
	private $user_style;

	/** @var int */
	private $forced_style;

	/** @var int */
	private $board_style;

	/** @var array */
	private $style;

	public function __construct(config $config, driver_interface $db, AuthorizationCheckerInterface $authorization_checker)
	{
		$this->db = $db;
		$this->config = $config;
		$this->authorization_checker = $authorization_checker;
	}

	public function get_style()
	{
		if ($this->style === null)
		{
			$style_id = $this->board_style = (int) $this->config['default_style'];

			if ($this->requested_style !== null
				&& (!$this->config['override_user_style'] || $this->authorization_checker->isGranted('a_styles')))
			{
				$style_id = $this->requested_style;
			}
			else if ($this->forced_style !== null)
			{
				$style_id = $this->forced_style;
			}
			else if ($this->config['override_user_style'])
			{
				$style_id = $this->board_style;
			}
			else if ($this->user_style !== null)
			{
				$style_id = $this->user_style;
			}

			$style = $this->get_style_from_db($style_id);

			// Fallback to user's standard style
			if (!$style && $style_id !== $this->user_style)
			{
				$style = $this->get_style_from_db($this->user_style);
			}

			// User has wrong style
			if (!$style && $style_id === $this->user_style)
			{
				// TODO: Update user style in db? (I don't really like it but it is the current behaviour)
				$style = $this->get_style_from_db($this->board_style);
			}

			if (!$style)
			{
				throw new runtime_exception('NO_STYLE_DATA');
			}

			$this->style = $style;
		}

		return $this->style;
	}

	/**
	 * @param int $style_id
	 *
	 * @return array|null
	 */
	private function get_style_from_db($style_id)
	{
		$sql = 'SELECT *
				FROM ' . STYLES_TABLE . " s
				WHERE s.style_id = $style_id";
		$result = $this->db->sql_query($sql, 3600);
		$style = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $style;
	}

	/**
	 * @param int $forced_style
	 */
	public function set_forced_style($forced_style)
	{
		$this->forced_style = (int) $forced_style;
		$this->style = null;
	}

	/**
	 * @param int $requested_style
	 */
	public function set_requested_style($requested_style)
	{
		$this->requested_style = (int) $requested_style;
		$this->style = null;
	}

	/**
	 * @param int $user_style
	 */
	public function set_user_style($user_style)
	{
		$this->user_style = (int) $user_style;
		$this->style = null;
	}
}
