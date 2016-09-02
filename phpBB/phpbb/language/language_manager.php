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

namespace phpbb\language;

use phpbb\db\driver\driver_interface;
use phpbb\exception\runtime_exception;
use Symfony\Component\Finder\Finder;

/**
 * Manages installed languages
 */
class language_manager
{
	/** @var array */
	private $languages_cache;

	/** @var driver_interface */
	private $db;

	public function __construct(driver_interface $db)
	{
		$this->db = $db;
	}

	/**
	 * Returns the database ID of a given installed language (identified by its iso code)
	 *
	 * @param string $iso
	 *
	 * @return int
	 */
	public function iso_to_id($iso)
	{
		$this->load_languages();

		if (!array_key_exists($iso, $this->languages_cache))
		{
			throw new runtime_exception('LANGUAGE_NOT_INSTALLED' , [$iso]);
		}

		return $this->languages_cache[$iso]['lang_id'];
	}

	private function load_languages()
	{
		if ($this->languages_cache !== null)
		{
			return;
		}

		$this->languages_cache = [];

		$sql = 'SELECT * FROM ' . LANG_TABLE ;
		$result = $this->db->sql_query($sql, 3600);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$row['lang_id'] = (int) $row['lang_id'];
			$this->languages_cache[$row['lang_iso']] = $row;
		}

		$this->db->sql_freeresult($result);
	}
}
