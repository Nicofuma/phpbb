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
use phpbb\legacy\array_wrapper;
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
			case 'data':
				$var = &$this->get_user()->data;
				return new array_wrapper($var);
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
		$this->add_lang($lang_set);

		if ($style_id !== false && $style_id !== null)
		{
			$this->style_helper->set_forced_style($style_id);
		}
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
	 * Determine which plural form we should use.
	 * For some languages this is not as simple as for English.
	 *
	 * @param $number        int|float   The number we want to get the plural case for. Float numbers are floored.
	 * @param $force_rule    mixed   False to use the plural rule of the language package
	 *                               or an integer to force a certain plural rule
	 * @return int|bool     The plural-case we need to use for the number plural-rule combination, false if $force_rule
	 * 					   was invalid.
	 *
	 * @deprecated: 3.2.0-dev (To be removed: 4.0.0)
	 */
	public function get_plural_form($number, $force_rule = false)
	{
		return $this->language->get_plural_form($number, $force_rule);
	}

	/**
	 * Add Language Items - use_db and use_help are assigned where needed (only use them to force inclusion)
	 *
	 * @param mixed $lang_set specifies the language entries to include
	 * @param bool $use_db internal variable for recursion, do not use	@deprecated 3.2.0-dev (To be removed: 3.3.0)
	 * @param bool $use_help internal variable for recursion, do not use	@deprecated 3.2.0-dev (To be removed: 3.3.0)
	 * @param string $ext_name The extension to load language from, or empty for core files
	 *
	 * Examples:
	 * <code>
	 * $lang_set = array('posting', 'help' => 'faq');
	 * $lang_set = array('posting', 'viewtopic', 'help' => array('bbcode', 'faq'))
	 * $lang_set = array(array('posting', 'viewtopic'), 'help' => array('bbcode', 'faq'))
	 * $lang_set = 'posting'
	 * $lang_set = array('help' => 'faq', 'db' => array('help:faq', 'posting'))
	 * </code>
	 *
	 * Note: $use_db and $use_help should be removed. The old function was kept for BC purposes,
	 * 		so the BC logic is handled here.
	 *
	 * @deprecated: 3.2.0-dev (To be removed: 4.0.0)
	 */
	public function add_lang($lang_set, $use_db = false, $use_help = false, $ext_name = '')
	{
		if (is_array($lang_set))
		{
			foreach ($lang_set as $key => $lang_file)
			{
				// Please do not delete this line.
				// We have to force the type here, else [array] language inclusion will not work
				$key = (string) $key;

				if ($key === 'db')
				{
					// This is never used
					$this->add_lang($lang_file, true, $use_help, $ext_name);
				}
				else if ($key === 'help')
				{
					$this->add_lang($lang_file, $use_db, true, $ext_name);
				}
				else if (!is_array($lang_file))
				{
					$this->set_lang($lang_file, $use_help, $ext_name);
				}
				else
				{
					$this->add_lang($lang_file, $use_db, $use_help, $ext_name);
				}
			}
		}
		else if ($lang_set)
		{
			$this->set_lang($lang_set, $use_help, $ext_name);
		}
	}

	/**
	 * BC function for loading language files
	 *
	 * @deprecated 3.2.0-dev (To be removed: 4.0.0)
	 */
	private function set_lang($lang_set, $use_help, $ext_name)
	{
		if (empty($ext_name))
		{
			$ext_name = null;
		}

		if ($use_help && strpos($lang_set, '/') !== false)
		{
			$component = dirname($lang_set) . '/help_' . basename($lang_set);

			if ($component[0] === '/')
			{
				$component = substr($component, 1);
			}
		}
		else
		{
			$component = (($use_help) ? 'help_' : '') . $lang_set;
		}

		$this->language->add_lang($component, $ext_name);
	}

	public function update_session_infos()
	{
		// TODO
	}
}
