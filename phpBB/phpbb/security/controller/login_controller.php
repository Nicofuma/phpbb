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
use phpbb\template\twig\environment;
use phpbb\template\twig\twig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class login_controller
{
	use translate_exception_trait;

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

	public function __construct(AuthenticationUtils $utils, TokenStorageInterface $token_storage,  helper $helper, language $language, twig $twig)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->twig = $twig;
		$this->utils = $utils;
		$this->token_storage = $token_storage;
	}

	/**
	 * Controller used to display the login form
	 *
	 * @return Response
	 */
	public function login_action()
	{
		$error = $this->utils->getLastAuthenticationError();

		$this->twig->assign_vars([
			'LOGIN_ERROR'		=> $error !== null ? $this->get_exception_message($this->language, $error) : null,
			'LOGIN_EXPLAIN'		=> null,

			//'U_SEND_PASSWORD' 		=> ($config['email_enable']) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=sendpassword') : '',
			//'U_RESEND_ACTIVATION'	=> ($config['require_activation'] == USER_ACTIVATION_SELF && $config['email_enable']) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=resend_act') : '',
			//'U_TERMS_USE'			=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=terms'),
			//'U_PRIVACY'				=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=privacy'),

			'S_DISPLAY_FULL_LOGIN'	=> true,
			//'S_HIDDEN_FIELDS' 		=> $s_hidden_fields,

			'S_ADMIN_AUTH'			=> false,
			'USERNAME'				=> $this->utils->getLastUsername(),
		]);

		return $this->helper->render('security/login.html', $this->language->lang('LOGIN'));
	}

	/**
	 * Controller only used to define the route used by the authenticator to check the user credentials
	 */
	public function login_check_action()
	{
		// Never executed
	}

	/**
	 * Controller used to log out the user (handled by the firewall)
	 */
	public function logout_action()
	{
		// Never executed
	}
}
