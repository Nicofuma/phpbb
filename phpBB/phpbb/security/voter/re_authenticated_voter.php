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

namespace phpbb\security\voter;

use phpbb\security\authentication_trust_resolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class re_authenticated_voter extends Voter
{
	const IS_REAUTHENTICATED = 'IS_REAUTHENTICATED';

	/** @var authentication_trust_resolver */
	private $authentication_trust_resolver;

	/**
	 * Constructor.
	 *
	 * @param authentication_trust_resolver $authentication_trust_resolver
	 */
	public function __construct(authentication_trust_resolver $authentication_trust_resolver)
	{
		$this->authentication_trust_resolver = $authentication_trust_resolver;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function supports($attribute, $subject)
	{
		return $attribute === self::IS_REAUTHENTICATED;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
	{
		return $this->authentication_trust_resolver->is_reauthenticated($token);
	}
}
