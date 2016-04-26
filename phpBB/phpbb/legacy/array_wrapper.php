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

namespace phpbb\legacy;

use phpbb\security\exception;

class array_wrapper implements \IteratorAggregate, \ArrayAccess, \Countable
{
	/** @var array */
	private $data;

	public function __construct(&$array)
	{
		$this->data = &$array;
	}

	public function offsetExists($offset)
	{
		return array_key_exists($offset, $this->data);
	}

	public function offsetGet($offset)
	{
		return $this->data[$offset];
	}

	public function offsetSet($offset, $value)
	{
		$this->data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}

	public function count()
	{
		return count($this->data);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}
}
