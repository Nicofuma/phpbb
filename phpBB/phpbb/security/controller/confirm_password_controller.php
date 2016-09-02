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

namespace phpbb\security\controller;

use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\language\translate_exception_trait;
use phpbb\passwords\manager;
use phpbb\security\listener\re_authenticated_listener;
use phpbb\security\token\re_authenticated_token;
use phpbb\template\twig\twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class confirm_password_controller
{
	use translate_exception_trait;
	use TargetPathTrait;

	const FORM_KEY = 'confirm_password';

	/** @var helper */
	private $helper;

	/** @var language */
	private $language;

	/** @var twig */
	private $twig;

	/** @var AuthenticationUtils */
	private $utils;

	/** @var TokenStorageInterface */
	private $token_storage;

	/** @var manager */
	private $password_manager;

	public function __construct(AuthenticationUtils $utils, TokenStorageInterface $token_storage,  helper $helper, language $language, twig $twig, manager $password_manager)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->twig = $twig;
		$this->utils = $utils;
		$this->token_storage = $token_storage;
		$this->password_manager = $password_manager;
	}

	/**
	 * @return Response
	 */
	public function confirm_password_action(Request $request)
	{
		// TODO : USE 2 different templates. Issue due to login button (same ID, same value) => the guard authenticator tried to handle the login form
		$error = null;

		if ($request->request->has(static::FORM_KEY))
		{
			$password = trim($request->request->get('password'));
			$user = $this->token_storage->getToken()->getUser();

			// TODO: dispatch event to handle count attempts (block if necessary) ?
			if ($this->password_manager->check($password, $user->getPassword(), $user->data))
			{
				$token = re_authenticated_token::createFromToken($this->token_storage->getToken());
				$this->token_storage->setToken($token);

				$target_path = $this->getTargetPath($request->getSession(), re_authenticated_listener::PROVIDER_KEY);
				$this->removeTargetPath($request->getSession(), re_authenticated_listener::PROVIDER_KEY);

				return new RedirectResponse($target_path);
			} else {
				$error = $this->language->lang('LOGIN_ERROR_PASSWORD');
			}
		}

		$this->twig->assign_vars([
			'LOGIN_ERROR' => $error ?: null,
		]);

		return $this->helper->render('security/confirm_password.html', $this->language->lang('CONFIRM_PASSWORD'));
	}
}
