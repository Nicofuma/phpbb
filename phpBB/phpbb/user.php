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

namespace phpbb;

use phpbb\language\language;
use phpbb\security\user\session_helper;
use phpbb\security\user\user_helper;
use phpbb\template\style_helper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class wrapping the new user class for BC purpose (the real user is retrieved from the token)
 *
 * @deprecated 3.3.0-dev Use the token storage instead
 *      ($container->get('security.token_storage')->getToken()->getUser()) (To be removed in 4.0.)
 */
class user
{
	/** @var TokenStorageInterface */
	private $token_storage;

	/** @var user_helper */
	private $user_helper;

	/** @var language */
	private $language;

	/** @var RequestStack */
	private $request_stack;

	/** @var session_helper */
	private $session_helper;

	/** @var style_helper */
	private $style_helper;

	/** @var system_helper */
	private $system_helper;

	public function __construct(
		TokenStorageInterface $token_storage,
		language $language,
		RequestStack $request_stack,
		user_helper $user_helper,
		session_helper $session_helper,
		style_helper $style_helper,
		system_helper $system_helper
	)
	{
		$this->token_storage = $token_storage;
		$this->user_helper = $user_helper;
		$this->language = $language;
		$this->request_stack = $request_stack;
		$this->session_helper = $session_helper;
		$this->style_helper = $style_helper;
		$this->system_helper = $system_helper;
	}

	public function __get($name)
	{
		switch ($name)
		{
			case 'lang':
				return $this->language->get_lang_array();
			case 'help':
				$lang_array = $this->language->get_lang_array();

				return $lang_array['__help'];
			case 'host':
				return $this->request_stack->getMasterRequest()->getHost();
			case 'browser':
				return $this->request_stack->getMasterRequest()->headers->get('User-Agent');
			case 'referer':
				return $this->request_stack->getMasterRequest()->headers->get('Referer');
			case 'forwarded_for':
				return $this->request_stack->getMasterRequest()->headers->get('X-Forwarded-For');
			case 'page':
				return $this->session_helper->get_current_page();
			case 'session_id':
				return $this->request_stack->getMasterRequest()->getSession()->getId();
			case 'style':
				return $this->style_helper->get_style();
			case 'load':
				return $this->system_helper->get_load();
		}

		if (!isset($this->get_user()->{$name}))
		{
			throw new \RuntimeException(sprintf('The attribute %s does not exist in the user class.', $name));
		}

		return $this->get_user()->{$name};
	}

	public function __set($name, $value)
	{
		if (!isset($this->get_user()->{$name}))
		{
			throw new \RuntimeException(sprintf('The attribute %s does not exist in the user class.', $name));
		}

		return $this->get_user()->{$name} = $value;
	}

	public function __call($name, $arguments)
	{
		if (method_exists($this->user_helper, $name))
		{
			array_unshift($arguments, $this->get_user());

			return call_user_func_array([$this->user_helper, $name], $arguments);
		}

		if (!method_exists($this->get_user(), $name))
		{
			throw new \RuntimeException(sprintf('The method %s does not exist in the user class.', $name));
		}

		return call_user_func_array([$this->get_user(), $name], $arguments);
	}

	private function get_user()
	{
		return $this->token_storage->getToken()->getUser();
	}

	/**
	 * More advanced language substitution
	 * Function to mimic sprintf() with the possibility of using phpBB's language system to substitute nullar/singular/plural forms.
	 * Params are the language key and the parameters to be substituted.
	 * This function/functionality is inspired by SHS` and Ashe.
	 *
	 * Example call: <samp>$user->lang('NUM_POSTS_IN_QUEUE', 1);</samp>
	 *
	 * If the first parameter is an array, the elements are used as keys and subkeys to get the language entry:
	 * Example: <samp>$user->lang(array('datetime', 'AGO'), 1)</samp> uses $user->lang['datetime']['AGO'] as language entry.
	 *
	 * @deprecated 3.2.0-dev (To be removed 4.0.0)
	 */
	public function lang()
	{
		$args = func_get_args();

		return call_user_func_array([$this->language, 'lang'], $args);
	}

	/**
	 * Specify/Get image
	 *
	 * @deprecated 3.3.0-dev Generate the img tag directly in the template (To be removed 4.0.0)
	 */
	public function img($img, $alt = '')
	{
		$title = '';

		if ($alt)
		{
			$alt = $this->language->lang($alt);
			$title = ' title="' . $alt . '"';
		}
		return '<span class="imageset ' . $img . '"' . $title . '>' . $alt . '</span>';
	}

	/**
	 * Setup basic user-specific items (style, language, ...)
	 *
	 * Now it only adds the language files. The other items are handled by some event listeners.
	 *
	 * @deprecated 3.2.0-dev (To be removed 4.0.0)
	 */
	public function setup($lang_set = false, $style_id = false)
	{
		$this->language->add_lang($lang_set);
	}

	public function update_session_infos()
	{
		// TODO
	}
}
