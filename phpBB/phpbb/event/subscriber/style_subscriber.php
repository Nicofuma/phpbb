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

namespace phpbb\event\subscriber;

use phpbb\template\style_helper;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class style_subscriber implements EventSubscriberInterface
{
	/** @var style_helper */
	private $style_helper;

	/** @var template */
	private $template;

	/** @var TokenStorageInterface */
	private $token_storage;

	/**
	 * Construct method
	 */
	public function __construct(TokenStorageInterface $token_storage, style_helper $style_helper, template $template)
	{
		$this->style_helper = $style_helper;
		$this->template = $template;
		$this->token_storage = $token_storage;
	}

	/**
	* @param GetResponseEvent $event
	*/
	public function on_kernel_request(GetResponseEvent $event)
	{
		$user = $this->token_storage->getToken()->getUser();

		if (array_key_exists('user_style', $user->data))
		{
			$this->style_helper->set_user_style($user->data['user_style']);
		}

		$this->style_helper->set_requested_style($event->getRequest()->get('style', null));

		$this->template->set_style();
	}

	static public function getSubscribedEvents()
	{
		return array(
			KernelEvents::REQUEST => ['on_kernel_request', 35],
		);
	}
}
