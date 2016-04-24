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

use phpbb\exception\exception_interface;

trait translate_exception_trait
{
	/**
	 * Returns the message of the given exception (translated if necessary)
	 * 
	 * @param language   $language
	 * @param \Exception $exception
	 *
	 * @return string
	 */
	public function get_exception_message(language $language, \Exception $exception)
	{
		$message = $exception->getMessage();

		if ($exception instanceof exception_interface)
		{
			$message = $language->lang_array($message, $exception->get_parameters());
		}

		return $message;
	}
}
