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

namespace phpbb\new_extension\dependency_resolver;

interface dependency_resolver_interface
{
	/**
	 * Resolves and returns the dependencies of an extension
	 *
	 * @param string $extension
	 *
	 * @return string[]
	 */
	public function resolve_dependencies($extension);

	/**
	 * Resolves and returns the installed dependent extensions of another extension
	 *
	 * @param string $extension
	 *
	 * @return string[]
	 */
	public function resolve_dependent($extension);
}
