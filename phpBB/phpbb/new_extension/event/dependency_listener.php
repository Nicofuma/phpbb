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

use phpbb\new_extension\dependency_resolver\dependency_resolver_interface;
use phpbb\new_extension\extension_manager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles extension's dependencies (enables dependencies or disables dependent extensions)
 */
class extension_dependency_listener implements EventSubscriberInterface
{
	/** @var dependency_resolver_interface */
	private $dependency_resolver;

	/** @var extension_manager */
	private $manager;

	public function __construct(extension_manager $manager, dependency_resolver_interface $dependency_resolver)
	{
		$this->dependency_resolver = $dependency_resolver;
		$this->manager = $manager;
	}

	public function on_extension_enable(extension_lifecycle_event $event)
	{
		$dependencies = $this->dependency_resolver->resolve_dependencies($event->get_extension());

		foreach ($dependencies as $dependency)
		{
			$this->manager->do_enable($dependency);
		}
	}

	public function on_extension_disable(extension_lifecycle_event $event)
	{
		$dependents = $this->dependency_resolver->resolve_dependent($event->get_extension());

		foreach ($dependents as $dependent)
		{
			$this->manager->do_disable($dependent);
		}
	}

	public static function getSubscribedEvents()
	{
		return [
			events::EXTENSION_ENABLE_EVENT => 'on_extension_enable',
			events::EXTENSION_DISABLE_EVENT => 'on_extension_disable',
		];
	}
}
