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

namespace phpbb\new_extension\event;

use Symfony\Component\EventDispatcher\Event;

class extension_lifecycle_event extends Event
{
	/** @var string */
	private $extension;

	/** @var array */
	private $context;

	public function __construct($extension, array $context = [])
	{
		$this->extension = $extension;
		$this->context = $context;
	}

	/**
	 * @return string
	 */
	public function get_extension()
	{
		return $this->extension;
	}

	/**
	 * @return array
	 */
	public function get_context()
	{
		return $this->context;
	}
}
