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

namespace phpbb\auth;

use phpbb\security\acl\acl_checker;
use phpbb\security\acl\acl_provider;
use phpbb\security\user\user;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
* Permission/Auth class
*/
class auth
{
	/** @var user */
	private $current_user;

	/** @var acl_checker */
	private $acl_checker;

	/** @var acl_provider */
	private $acl_provider;

	/** @var TokenStorageInterface */
	private $token_storage;

	public function __construct()
	{
		global $phpbb_container;

		$this->container = $phpbb_container;
		$this->acl_checker = $phpbb_container->get('security.acl.checker');
		$this->acl_provider = $phpbb_container->get('security.acl.provider');
		$this->token_storage = $phpbb_container->get('security.token_storage');
	}

	/**
	 * Returns the current user (either the one set by a call to acl() or the user attached to the current security token
	 *
	 * @return user
	 */
	private function current_user()
	{
		if ($this->current_user !== null)
		{
			return $this->current_user;
		}

		return $this->token_storage->getToken()->getUser();
	}

	/**
	* Init permissions
	*/
	public function acl(&$userdata)
	{
		$this->current_user = user::createFromRawArray($userdata);
	}

	/**
	* Look up an option
	* if the option is prefixed with !, then the result becomes negated
	*
	* If a forum id is specified the local option will be combined with a global option if one exist.
	* If a forum id is not specified, only the global option will be checked.
	*/
	public function acl_get($opt, $f = 0)
	{
		return $this->acl_checker->acl_get($this->current_user(), $opt, $f);
	}

	/**
	* Get forums with the specified permission setting
	*
	* @param string $opt The permission name to lookup. If prefixed with !, the result is negated.
	* @param bool	$clean set to true if only values needs to be returned which are set/unset
	*
	* @return array Contains the forum ids with the specified permission set to true.
					This is a nested array: array => forum_id => permission => true
	*/
	public function acl_getf($opt, $clean = false)
	{
		return $this->acl_checker->acl_getf($this->current_user(), $opt, $clean);
	}

	/**
	* Get local permission state for any forum.
	*
	* Returns true if user has the permission in one or more forums, false if in no forum.
	* If global option is checked it returns the global state (same as acl_get($opt))
	* Local option has precedence...
	*/
	public function acl_getf_global($opt)
	{
		return $this->acl_checker->acl_getf_global($this->current_user(), $opt);
	}

	/**
	* Get permission settings (more than one)
	*/
	public function acl_gets()
	{
		$args = func_get_args();
		array_unshift($args, $this->current_user());

		return call_user_func_array([$this->acl_checker, 'acl_get'], $args);
	}

	/**
	* Get permission listing based on user_id/options/forum_ids
	*
	* Be careful when using this function with permissions a_, m_, u_ and f_ !
	* It may not work correctly. When a user group grants an a_* permission,
	* e.g. a_foo, but the user's a_foo permission is set to "Never", then
	* the user does not in fact have the a_ permission.
	* But the user will still be listed as having the a_ permission.
	*
	* For more information see: http://tracker.phpbb.com/browse/PHPBB3-10252
	*/
	public function acl_get_list($user_id = false, $opts = false, $forum_id = false)
	{
		return $this->acl_checker->acl_get_list($user_id, $opts, $forum_id);
	}

	/**
	* Clear one or all users cached permission settings
	*/
	public function acl_clear_prefetch($user_id = false)
	{
		return $this->acl_provider->acl_clear_prefetch($user_id);
	}

	/**
	* Get assigned roles
	*/
	public function acl_role_data($user_type, $role_type, $ug_id = false, $forum_id = false)
	{
		return $this->acl_provider->acl_role_data($user_type, $role_type, $ug_id, $forum_id);
	}

	/**
	* Get raw acl data based on user/option/forum
	*/
	public function acl_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		return $this->acl_provider->acl_raw_data($user_id, $opts, $forum_id);
	}

	/**
	* Get raw user based permission settings
	*/
	public function acl_user_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		return $this->acl_provider->acl_user_raw_data($user_id, $opts, $forum_id);
	}

	/**
	* Get raw group based permission settings
	*/
	public function acl_group_raw_data($group_id = false, $opts = false, $forum_id = false)
	{
		return $this->acl_provider->acl_group_raw_data($group_id, $opts, $forum_id);
	}

	/**
	* Get raw acl data based on user for caching user_permissions
	* This function returns the same data as acl_raw_data(), but without the user id as the first key within the array.
	*/
	public function acl_raw_data_single_user($user_id)
	{
		return $this->acl_provider->acl_raw_data_single_user($user_id);
	}

	/**
	* Authentication plug-ins is largely down to Sergey Kanareykin, our thanks to him.
	*/
	public function login($username, $password, $autologin = false, $viewonline = 1, $admin = 0)
	{
		global $db, $user, $phpbb_root_path, $phpEx, $phpbb_container;
		global $phpbb_dispatcher;

		/* @var $provider_collection \phpbb\auth\provider_collection */
		$provider_collection = $phpbb_container->get('auth.provider_collection');

		$provider = $provider_collection->get_provider();
		if ($provider)
		{
			$login = $provider->login($username, $password);

			// If the auth module wants us to create an empty profile do so and then treat the status as LOGIN_SUCCESS
			if ($login['status'] == LOGIN_SUCCESS_CREATE_PROFILE)
			{
				// we are going to use the user_add function so include functions_user.php if it wasn't defined yet
				if (!function_exists('user_add'))
				{
					include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
				}

				user_add($login['user_row'], (isset($login['cp_data'])) ? $login['cp_data'] : false);

				$sql = 'SELECT user_id, username, user_password, user_passchg, user_email, user_type
					FROM ' . USERS_TABLE . "
					WHERE username_clean = '" . $db->sql_escape(utf8_clean_string($username)) . "'";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					return array(
						'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
						'error_msg'		=> 'AUTH_NO_PROFILE_CREATED',
						'user_row'		=> array('user_id' => ANONYMOUS),
					);
				}

				$login = array(
					'status'	=> LOGIN_SUCCESS,
					'error_msg'	=> false,
					'user_row'	=> $row,
				);
			}

			// If the auth provider wants us to link an empty account do so and redirect
			if ($login['status'] == LOGIN_SUCCESS_LINK_PROFILE)
			{
				// If this status exists a fourth field is in the $login array called 'redirect_data'
				// This data is passed along as GET data to the next page allow the account to be linked

				$params = array('mode' => 'login_link');
				$url = append_sid($phpbb_root_path . 'ucp.' . $phpEx, array_merge($params, $login['redirect_data']));

				redirect($url);
			}

			/**
			 * Event is triggered after checking for valid username and password, and before the actual session creation.
			 *
			 * @event core.auth_login_session_create_before
			 * @var	array	login				Variable containing login array
			 * @var	bool	admin				Boolean variable whether user is logging into the ACP
			 * @var	string	username			Username of user to log in
			 * @var	bool	autologin			Boolean variable signaling whether login is triggered via auto login
			 * @since 3.1.7-RC1
			 */
			$vars = array(
				'login',
				'admin',
				'username',
				'autologin',
			);
			extract($phpbb_dispatcher->trigger_event('core.auth_login_session_create_before', compact($vars)));

			// If login succeeded, we will log the user in... else we pass the login array through...
			if ($login['status'] == LOGIN_SUCCESS)
			{
				$old_session_id = $user->session_id;

				if ($admin)
				{
					global $SID, $_SID;

					$cookie_expire = time() - 31536000;
					$user->set_cookie('u', '', $cookie_expire);
					$user->set_cookie('sid', '', $cookie_expire);
					unset($cookie_expire);

					$SID = '?sid=';
					$user->session_id = $_SID = '';
				}

				$result = $user->session_create($login['user_row']['user_id'], $admin, $autologin, $viewonline);

				// Successful session creation
				if ($result === true)
				{
					// If admin re-authentication we remove the old session entry because a new one has been created...
					if ($admin)
					{
						// the login array is used because the user ids do not differ for re-authentication
						$sql = 'DELETE FROM ' . SESSIONS_TABLE . "
							WHERE session_id = '" . $db->sql_escape($old_session_id) . "'
							AND session_user_id = {$login['user_row']['user_id']}";
						$db->sql_query($sql);
					}

					return array(
						'status'		=> LOGIN_SUCCESS,
						'error_msg'		=> false,
						'user_row'		=> $login['user_row'],
					);
				}

				return array(
					'status'		=> LOGIN_BREAK,
					'error_msg'		=> $result,
					'user_row'		=> $login['user_row'],
				);
			}

			return $login;
		}

		trigger_error('Authentication method not found', E_USER_ERROR);
	}

	/**
	 * Retrieves data wanted by acl function from the database for the
	 * specified user.
	 *
	 * @param int $user_id User ID
	 * @return array User attributes
	 */
	public function obtain_user_data($user_id)
	{
		global $db;

		$sql = 'SELECT user_id, username, user_permissions, user_type
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $db->sql_query($sql);
		$user_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return $user_data;
	}
}
