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

namespace phpbb\security\user;

use phpbb\security\exception;
use phpbb\user_loader;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class phpbb_database_provider implements UserProviderInterface
{
	/** @var user_loader */
	private $user_loader;

	public function __construct(user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}

	/**
	 * {@inheritdoc}
	 */
	public function loadUserByUsername($username)
	{
		$user_id = $this->user_loader->load_user_by_username($username);

		if ($user_id === ANONYMOUS)
		{
			throw new exception\unsername_not_found_exception('SECURITY_ERROR_USERNAME_NOTFOUND', ['user' => $username]);
		}

		return user::createFromRawArray($this->user_loader->get_user($user_id));
	}

	/**
	 * {@inheritdoc}
	 */
	public function refreshUser(UserInterface $user)
	{
		if (!$user instanceof user)
		{
			throw new exception\unsupported_user_exception('SECURITY_ERROR_UNSUPPORTED_USER', ['class' => get_class($user)]);
		}

		$this->user_loader->load_users([$user->get_id()]);
		$new_user = user::createFromRawArray($this->user_loader->get_user($user->get_id()));

		if ($new_user->is_anonymous() && !$user->is_anonymous())
		{
			throw new exception\unsername_not_found_exception('SECURITY_ERROR_USERNAME_NOTFOUND', ['user' => $user->getUsername()]);
		}

		return $new_user;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsClass($class)
	{
		return $class === user::class;
	}
}
