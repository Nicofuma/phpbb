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
	// Able to add new options (up to id 31)
	private static $keyoptions = [
		'viewimg' => 0,
		'viewflash' => 1,
		'viewsmilies' => 2,
		'viewsigs' => 3,
		'viewavatars' => 4,
		'viewcensors' => 5,
		'attachsig' => 6,
		'bbcode' => 8,
		'smilies' => 9,
		'sig_bbcode' => 15,
		'sig_smilies' => 16,
		'sig_links' => 17
	];

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

	/**
	 * Get option bit field from user options.
	 *
	 * @param int $key option key, as defined in $keyoptions property.
	 * @param int|bool $data bit field value to use, or false to use $this->data['user_options']
	 *
	 * @return bool true if the option is set in the bit field, false otherwise
	 */
	public function optionget($key, $data = false)
	{
		$var = ($data !== false) ? $data : $this->data['user_options'];
		return phpbb_optionget(static::$keyoptions[$key], $var);
	}

	/**
	 * Set option bit field for user options.
	 *
	 * @param int $key Option key, as defined in $keyoptions property.
	 * @param bool $value True to set the option, false to clear the option.
	 * @param int|bool $data Current bit field value, or false to use $this->data['user_options']
	 *
	 * @return int|bool If $data is false, the bit field is modified and
	 *                  written back to $this->data['user_options'], and
	 *                  return value is true if the bit field changed and
	 *                  false otherwise. If $data is not false, the new
	 *                  bitfield value is returned.
	 */
	public function optionset($key, $value, $data = false)
	{
		$var = ($data !== false) ? $data : $this->data['user_options'];

		$new_var = phpbb_optionset(static::$keyoptions[$key], $value, $var);

		if ($data === false)
		{
			if ($new_var != $var)
			{
				$this->data['user_options'] = $new_var;
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return $new_var;
		}
	}
}
