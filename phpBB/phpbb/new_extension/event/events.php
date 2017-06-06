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

final class events
{
	/**
	 * Event triggered when an extension is going to be installed
	 */
	const EXTENSION_INSTALL_EVENT = 'extension.install';

	/**
	 * Event triggered when an extension has been successfully installed
	 */
	const EXTENSION_INSTALLED_EVENT = 'extension.installed';

	/**
	 * Event triggered when an extension is going to be enabled
	 */
	const EXTENSION_ENABLE_EVENT = 'extension.enable';

	/**
	 * Event triggered when an extension has been enabled
	 */
	const EXTENSION_DO_ENABLED_EVENT = 'extension.do_enabled';

	/**
	 * Event triggered when the enable process of an extension is complete (including all dependencies if necessary)
	 */
	const EXTENSIONS_ENABLED_EVENT = 'extension.enabled';

	/**
	 * Event triggered when an extension is going to be disabled
	 */
	const EXTENSION_DISABLE_EVENT = 'extension.disable';

	/**
	 * Event triggered when an extension has been disabled
	 */
	const EXTENSION_DO_DISABLED_EVENT = 'extension.do_disabled';

	/**
	 * Event triggered when the disable process of an extension is complete (including all dependencies if necessary)
	 */
	const EXTENSIONS_DISABLED_EVENT = 'extension.disabled';

	/**
	 * Event triggered when an extension is going to be purged
	 */
	const EXTENSION_PURGE_EVENT = 'extension.purge';

	/**
	 * Event triggered when an extension has been purged
	 */
	const EXTENSION_DO_PURGED_EVENT = 'extension.do_purged';

	/**
	 * Event triggered when the purge process of an extension is complete (including all dependencies if necessary)
	 */
	const EXTENSIONS_PURGED_EVENT = 'extension.purged';
}
