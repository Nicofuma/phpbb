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

namespace phpbb\security\token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Token identifying a re authenticated token (password confirmed per example)
 */
class re_authenticated_token extends AbstractToken
{
	private $providerKey;

	/**
	 * @param UserInterface            $user        The user!
	 * @param string                   $providerKey The provider (firewall) key
	 * @param RoleInterface[]|string[] $roles       An array of roles
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(UserInterface $user, $providerKey, array $roles)
	{
		parent::__construct($roles);

		if (empty($providerKey))
		{
			throw new \InvalidArgumentException('$providerKey (i.e. firewall key) must not be empty.');
		}

		$this->setUser($user);
		$this->providerKey = $providerKey;

		// this token is meant to be used after authentication success, so it is always authenticated
		// you could set it as non authenticated later if you need to
		parent::setAuthenticated(true);
	}

	/**
	 * This is meant to be only an authenticated token, where credentials
	 * have already been used and are thus cleared.
	 *
	 * {@inheritdoc}
	 */
	public function getCredentials()
	{
		return [];
	}

	/**
	 * Returns the provider (firewall) key.
	 *
	 * @return string
	 */
	public function get_provider_key()
	{
		return $this->providerKey;
	}

	/**
	 * {@inheritdoc}
	 */
	public function serialize()
	{
		return serialize(array($this->providerKey, parent::serialize()));
	}

	/**
	 * {@inheritdoc}
	 */
	public function unserialize($serialized)
	{
		list($this->providerKey, $parentStr) = unserialize($serialized);
		parent::unserialize($parentStr);
	}

	/**
	 * Creates an re authenticated token from an existing token.
	 *
	 * @param TokenInterface $token
	 *
	 * @return static
	 */
	public static function createFromToken(TokenInterface $token)
	{
		$provider_key = method_exists($token, 'getProviderKey') ? $token->getProviderKey() : null;

		return new static($token->getUser(), $provider_key, $token->getRoles());
	}
}
