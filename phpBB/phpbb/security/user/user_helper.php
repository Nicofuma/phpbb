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

use phpbb\config\config;
use phpbb\datetime;
use phpbb\language\language;
use phpbb\user_loader;

class user_helper
{
	/** @var language */
	private $language;

	/** @var string */
	private $datetime_class;

	/** @var config */
	private $config;

	/** @var user_loader */
	private $user_loader;

	/**
	 * user_helper constructor.
	 *
	 * @param user_loader $user_loader
	 * @param config      $config
	 * @param language    $language
	 * @param string      $datetime_class
	 */
	public function __construct(user_loader $user_loader, config $config, language $language, $datetime_class)
	{
		$this->language = $language;
		$this->datetime_class = $datetime_class;
		$this->config = $config;
		$this->user_loader = $user_loader;
	}

	/**
	 * Format user date
	 *
	 * @param user $user The user
	 * @param int $gmepoch unix timestamp
	 * @param string|bool $format date format in date() notation. | used to indicate relative dates, for example |d m Y|, h:i is translated to Today, h:i.
	 * @param bool $forcedate force non-relative date format.
	 *
	 * @return datetime translated date
	 */
	public function format_date($user, $gmepoch, $format = false, $forcedate = false)
	{
		static $utc;

		if (!isset($utc))
		{
			$utc = new \DateTimeZone('UTC');
		}

		/** @var datetime $time */
		$time = new $this->datetime_class($this, '@' . (int) $gmepoch, $utc);
		$time->setTimezone($user->get_timezone());

		return $time->format($format, $forcedate);
	}

	/**
	 * Create a \phpbb\datetime object in the context of the current user
	 *
	 * @param user $user The user
	 * @param string $time String in a format accepted by strtotime().
	 * @param \DateTimeZone $timezone Time zone of the time.
	 *
	 * @return datetime Date time object linked to the current users locale
	 *
	 * @since 3.1
	 */
	public function create_datetime(user $user, $time = 'now', \DateTimeZone $timezone = null)
	{
		$timezone = $timezone ?: $user->get_timezone();

		return new $this->datetime_class($this, $time, $timezone);
	}

	/**
	 * Get the UNIX timestamp for a datetime in the users timezone, so we can store it in the database.
	 *
	 * @param	user			$user The user
	 * @param	string			$format		Format of the entered date/time
	 * @param	string			$time		Date/time with the timezone applied
	 * @param	\DateTimeZone	$timezone	Timezone of the date/time, falls back to timezone of current user
	 *
	 * @return int Returns the unix timestamp
	 */
	public function get_timestamp_from_format(user $user, $format, $time, \DateTimeZone $timezone = null)
	{
		$timezone = $timezone ?: $user->get_timezone();
		$date = \DateTime::createFromFormat($format, $time, $timezone);

		return ($date !== false) ? $date->format('U') : false;
	}

	/**
	 * Creates an anonymous user and initialize it according to the board config
	 *
	 * @return user
	 */
	public function create_anonymous_user()
	{
		$data = $this->user_loader->get_user(ANONYMOUS);

		$data['user_lang'] = basename($this->config['default_lang']);
		$data['user_dateformat'] = $this->config['default_dateformat'];
		$data['user_timezone'] = $this->config['board_timezone'];

		return user::createFromRawArray($data);
	}
}
