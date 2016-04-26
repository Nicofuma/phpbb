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

namespace phpbb\security\guard;

use phpbb\passwords\manager;
use phpbb\routing\helper;
use phpbb\security\exception;
use phpbb\security\user\user;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;

class form_login_authenticator extends AbstractFormLoginAuthenticator
{
	/** @var helper */
	private $routing_helper;

	/** @var manager */
	private $password_manager;

	public function __construct(helper $routing_helper, manager $password_manager)
	{
		$this->routing_helper = $routing_helper;
		$this->password_manager = $password_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getLoginUrl()
	{
		return $this->routing_helper->route('phpbb_user_login');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultSuccessRedirectUrl()
	{
		return append_sid('');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCredentials(Request $request)
	{
		// TODO: dispatch event to handle attempts count (check if the captcha needs to be checked and then complete th credentials)
		if ($request->getMethod() === Request::METHOD_POST && $request->request->get('login', null) === 'Login')
		{
			$username = trim($request->request->get('username'));
			if (!$username)
			{
				throw new exception\bad_credentials_exception('LOGIN_ERROR_USERNAME', ['username' => $username]);
			}

			$password = trim($request->request->get('password'));
			if (!$password)
			{
				throw new exception\bad_credentials_exception('LOGIN_ERROR_PASSWORD', ['password' => $password]);
			}

			return [
				'username' => $username,
				'password' => $password,
			];
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUser($credentials, UserProviderInterface $userProvider)
	{
		return $userProvider->loadUserByUsername($credentials['username']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function checkCredentials($credentials, UserInterface $user)
	{
		if (!$user instanceof user)
		{
			throw new exception\unsupported_user_exception('SECURITY_ERROR_UNSUPPORTED_USER', ['class' => get_class($user)]);
		}

		// TODO: display event to handle count attempts (block or check captcha)
		return $this->password_manager->check($credentials['password'], $user->getPassword(), $user->data);
	}

	/**
	 * {@inheritdoc}
	 */
	public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
	{
		// TODO: count attempts (dispatch event)
		return parent::onAuthenticationFailure($request, $exception);
	}

	/**
	 * {@inheritdoc}
	 */
	public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
	{
		// TODO: reset attempts (dispatch event)
		return parent::onAuthenticationSuccess($request, $token, $providerKey);
	}
}
