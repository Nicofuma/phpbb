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

namespace phpbb\security\listener;

use phpbb\security\user\user;
use phpbb\user_loader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class anonymous_authentication_listener implements ListenerInterface
{
	/** @var TokenStorageInterface */
	private $tokenStorage;

	/** @var string */
	private $secret;

	/** @var user_loader */
	private $user_loader;

	/** @var null|AuthenticationManagerInterface */
	private $authenticationManager;

	/** @var null|LoggerInterface */
	private $logger;

	/**
	 * @param TokenStorageInterface $tokenStorage
	 * @param string $secret
	 * @param user_loader $user_loader
	 * @param LoggerInterface|null $logger
	 * @param AuthenticationManagerInterface|null $authenticationManager
	 */
	public function __construct(TokenStorageInterface $tokenStorage, $secret, user_loader $user_loader, LoggerInterface $logger = null, AuthenticationManagerInterface $authenticationManager = null)
	{
		$this->tokenStorage = $tokenStorage;
		$this->secret = $secret;
		$this->user_loader = $user_loader;
		$this->authenticationManager = $authenticationManager;
		$this->logger = $logger;
	}

	/**
	 * Handles anonymous authentication.
	 *
	 * @param GetResponseEvent $event A GetResponseEvent instance
	 */
	public function handle(GetResponseEvent $event)
	{
		if (null !== $this->tokenStorage->getToken())
		{
			return;
		}

		try {
			$anonymous_user = user::createFromRawArray($this->user_loader->get_user(ANONYMOUS));
			$token = new AnonymousToken($this->secret, $anonymous_user, array());
			if (null !== $this->authenticationManager)
			{
				$token = $this->authenticationManager->authenticate($token);
			}

			$this->tokenStorage->setToken($token);

			if (null !== $this->logger)
			{
				$this->logger->info('Populated the TokenStorage with an anonymous Token.');
			}
		}
		catch (AuthenticationException $failed)
		{
			if (null !== $this->logger)
			{
				$this->logger->info('Anonymous authentication failed.', array('exception' => $failed));
			}
		}
	}
}
