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

use phpbb\security\user\user_helper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

/**
 * Automatically adds a Token with the anonymous user if none is already present.
 *
 * @see \Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener
 */
class anonymous_authentication_listener implements ListenerInterface
{
	/** @var TokenStorageInterface */
	private $tokenStorage;

	/** @var string */
	private $secret;

	/** @var user_helper */
	private $user_helper;

	/** @var null|AuthenticationManagerInterface */
	private $authenticationManager;

	/** @var null|LoggerInterface */
	private $logger;

	/**
	 * @param TokenStorageInterface $tokenStorage
	 * @param string $secret
	 * @param user_helper $user_helper
	 * @param LoggerInterface|null $logger
	 * @param AuthenticationManagerInterface|null $authenticationManager
	 */
	public function __construct(TokenStorageInterface $tokenStorage, $secret, user_helper $user_helper, LoggerInterface $logger = null, AuthenticationManagerInterface $authenticationManager = null)
	{
		$this->tokenStorage = $tokenStorage;
		$this->secret = $secret;
		$this->user_helper = $user_helper;
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
			$anonymous_user = $this->user_helper->create_anonymous_user();
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
