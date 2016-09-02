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

namespace phpbb\security;

use phpbb\security\token\re_authenticated_token;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class authentication_trust_resolver extends AuthenticationTrustResolver
{
	public function is_reauthenticated(TokenInterface $token = null)
	{
		if ($token === null)
		{
			return false;
		}

		return $token instanceof re_authenticated_token;
	}
}
