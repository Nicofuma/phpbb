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

namespace phpbb\event;

/**
* Extension of the Symfony2 EventDispatcher
*
* It provides an additional `trigger_event` method, which
* gives some syntactic sugar for dispatching events. Instead
* of creating the event object, the method will do that for
* you.
*
* Example:
*
* $vars = array('page_title');
* foreach ($vars as $var) {
 	if(isset(${$var})) {
 		ob_start();
 		xdebug_debug_zval($var);
 		$info = ob_get_clean();
 		$__match__ = [];
 		preg_match("(\(refcount=(\d+), is_ref=(\d+)\))", $info, $__match__);
 		$info = array("refcount" => $__match__[1], "is_ref" => $__match__[2]);
 		if ((boolean)$info["is_ref"]) {
 			file_put_contents("/tmp/event_refs", __FILE__ . ":" . __LINE__ . " => " . $var . " is a reference
", FILE_APPEND);
 		}
 	} else {
 		file_put_contents("/tmp/event_refs", __FILE__ . ":" . __LINE__ . " => " . $var . " is not defined
", FILE_APPEND);
 	}
 }
 extract($phpbb_dispatcher->trigger_event('core.index', compact($vars)));
*
*/
interface dispatcher_interface extends \Symfony\Component\EventDispatcher\EventDispatcherInterface
{
	/**
	* Construct and dispatch an event
	*
	* @param string $eventName	The event name
	* @param array $data		An array containing the variables sending with the event
	* @return mixed
	*/
	public function trigger_event($eventName, $data = array());

	/**
	 * Disable the event dispatcher.
	 */
	public function disable();

	/**
	 * Enable the event dispatcher.
	 */
	public function enable();
}
