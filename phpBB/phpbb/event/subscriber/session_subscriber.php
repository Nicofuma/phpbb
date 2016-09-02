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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the session in the request.
 */
class session_subscriber extends BaseSessionListener
{
	/** @var ContainerInterface */
	private $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	/**
	 * {@inheritdoc}
	 */
	public function onKernelRequest(GetResponseEvent $event)
	{
		parent::onKernelRequest($event);

		// Sets the session id if available (it may not be accessible to php due to disable_super_globals
		if ($event->isMasterRequest())
		{
			$request = $event->getRequest();

			if ($request->hasSession())
			{
				$session = $request->getSession();
				$cookie_name = $session->getName();

				if ($request->cookies->has($cookie_name))
				{
					// Required because of disable_super_globals()
					$request->getSession()->setId($request->cookies->get($cookie_name));
				}
				else
				{
					// Handle legacy sessions ids cookies
					// @deprecated 3.3.0-dev (To be removed in 4.0)
					if ($request->cookies->has('sid'))
					{
						$request->getSession()->setId($request->cookies->get('sid'));
					}
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getSession()
	{
		if (!$this->container->has('session')) {
			return;
		}

		return $this->container->get('session');
	}
}
