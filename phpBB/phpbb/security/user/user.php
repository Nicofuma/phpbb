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

namespace phpbb\security\user;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class representing the current user
 *
 * /!\ Warning: the public access of  the properties is deprecated. They will be private in 4.0.0
 */
class user implements UserInterface
{
	/** @var array */
	public $data;

	/** @var \DateTimeZone */
	public $timezone;

	/** @var string */
	public $lang_name;

	/** @var string */
	public $date_format;

	public static function createFromRawArray(array $data)
	{
		$user = new self();
		$user->data = $data;

		$user->date_format = $user->data['user_dateformat'];
		$user->lang_name = $user->data['user_lang'];

		try
		{
			$user->timezone = new \DateTimeZone($user->data['user_timezone']);
		}
		catch (\Exception $e)
		{
			// If the timezone the user has selected is invalid, we fall back to UTC.
			$user->timezone = new \DateTimeZone('UTC');
		}

		return $user;
	}

	/**
	 * @return int
	 */
	public function get_id()
	{
		return (int) $this->data['user_id'];
	}

	/**
	 * @return bool
	 */
	public function is_anonymous()
	{
		return $this->get_id() === ANONYMOUS;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRoles()
	{
		return ['ROLE_USER'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPassword()
	{
		return $this->data['user_password'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSalt()
	{
		return array_key_exists('user_passwd_salt', $this->data) ? $this->data['user_passwd_salt'] : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsername()
	{
		return $this->data['username'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function eraseCredentials()
	{
		$this->data['password'] = '******';
	}

	/**
	 * @return \DateTimeZone
	 */
	public function get_timezone()
	{
		return $this->timezone;
	}
}
