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

namespace phpbb\security\acl;

use phpbb\security\user\user;

/**
 * Checks the acls of a given user
 *
 * Warning: Currently marked as internal as it has been introduced only to introduce the user object support.
 *          It will most likely change in a near future.
 *
 * @internal
 */
class acl_checker
{
	/** @var acl_provider */
	private $acl_provider;

	public function __construct(acl_provider $acl_provider)
	{
		$this->acl_provider = $acl_provider;
	}

	/**
	 * Look up an option
	 * if the option is prefixed with !, then the result becomes negated
	 *
	 * If a forum id is specified the local option will be combined with a global option if one exist.
	 * If a forum id is not specified, only the global option will be checked.
	 *
	 * @param user $user
	 * @param string $opt
	 * @param int $f
	 *
	 * @return bool
	 */
	public function acl_get(user $user, $opt, $f = 0)
	{
		$negate = false;

		if (strpos($opt, '!') === 0)
		{
			$negate = true;
			$opt = substr($opt, 1);
		}

		$acls = $this->acl_provider->get_acl($user);

		// We combine the global/local option with an OR because some options are global and local.
		// If the user has the global permission the local one is true too and vice versa
		$result = false;

		// Is this option a global permission setting?
		if ($this->acl_provider->has_option('global', $opt))
		{
			if (isset($acls[0]))
			{
				$result = $acls[0][$this->acl_provider->get_option('global', $opt)];
			}
		}

		// Is this option a local permission setting?
		// But if we check for a global option only, we won't combine the options...
		if ($f != 0 && $this->acl_provider->has_option('local', $opt))
		{
			$opt_id = $this->acl_provider->get_option('local', $opt);
			if (isset($acls[$f], $acls[$f][$opt_id]))
			{
				$result |= $acls[$f][$opt_id];
			}
		}

		// Founder always has all global options set to true...
		return ($negate) ? !$result : $result;
	}

	/**
	 * Get forums with the specified permission setting
	 *
	 * @param user $user
	 * @param string $opt The permission name to lookup. If prefixed with !, the result is negated.
	 * @param bool	$clean set to true if only values needs to be returned which are set/unset
	 *
	 * @return array Contains the forum ids with the specified permission set to true.
	 *                  This is a nested array: array => forum_id => permission => true
	 */
	public function acl_getf(user $user, $opt, $clean = false)
	{
		$acl_f = array();
		$negate = false;

		if (strpos($opt, '!') === 0)
		{
			$negate = true;
			$opt = substr($opt, 1);
		}

		// If we retrieve a list of forums not having permissions in, we need to get every forum_id
		if ($negate)
		{
			$forum_ids = $this->acl_provider->get_user_forums_id($user);
		}

		if ($this->acl_provider->has_option('local', $opt))
		{
			$acls = $this->acl_provider->get_acl($user);

			foreach ($acls as $f => $bitstring)
			{
				// Skip global settings
				if (!$f)
				{
					continue;
				}

				$allowed = $this->acl_get($user, $opt, $f);

				if (!$clean)
				{
					$acl_f[$f][$opt] = ($negate) ? !$allowed : $allowed;
				}
				else
				{
					if (($negate && !$allowed) || (!$negate && $allowed))
					{
						$acl_f[$f][$opt] = 1;
					}
				}
			}
		}

		// If we get forum_ids not having this permission, we need to fill the remaining parts
		if ($negate && count($forum_ids))
		{
			foreach ($forum_ids as $f)
			{
				$acl_f[$f][$opt] = 1;
			}
		}

		return $acl_f;
	}

	/**
	 * Get local permission state for any forum.
	 *
	 * Returns true if user has the permission in one or more forums, false if in no forum.
	 * If global option is checked it returns the global state (same as acl_get($opt))
	 * Local option has precedence...
	 *
	 * @param user $user
	 * @param string|array $opt
	 *
	 * @return bool
	 */
	public function acl_getf_global(user $user, $opt)
	{
		if (is_array($opt))
		{
			// evaluates to true as soon as acl_getf_global is true for one option
			foreach ($opt as $check_option)
			{
				if ($this->acl_getf_global($user, $check_option))
				{
					return true;
				}
			}

			return false;
		}

		$acls = $this->acl_provider->get_acl($user);

		if ($this->acl_provider->has_option('local', $opt))
		{
			foreach ($acls as $f => $bitstring)
			{
				// Skip global settings
				if ($f == 0)
				{
					continue;
				}

				// as soon as the user has any permission we're done so return true
				if ($this->acl_get($user, $opt, $f))
				{
					return true;
				}
			}
		}
		else if ($this->acl_provider->has_option('global', $opt))
		{
			return $this->acl_get($user, $opt);
		}

		return false;
	}

	/**
	 * Get permission settings (more than one)
	 *
	 * usage:
	 *   - acl_gets($user, ['m_', 'a_'], $forum_id)
	 *   - acl_gets($user, 'm_', 'a_')
	 *   - acl_gets($user, 'm_', 'a_', $forum_id)
	 */
	public function acl_gets()
	{
		$args = func_get_args();
		$user = array_shift($args);
		$f = array_pop($args);

		if (!is_numeric($f))
		{
			$args[] = $f;
			$f = 0;
		}

		// alternate syntax: acl_gets(array('m_', 'a_'), $forum_id)
		if (is_array($args[0]))
		{
			$args = $args[0];
		}

		$acl = 0;
		foreach ($args as $opt)
		{
			$acl |= $this->acl_get($user, $opt, $f);
		}

		return $acl;
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
	 *
	 * @param bool|int|array $user_ids
	 * @param bool|string|array $opts
	 * @param bool|int|array $forum_ids
	 *
	 * @return array
	 */
	public function acl_get_list($user_ids = false, $opts = false, $forum_ids = false)
	{
		if ($user_ids !== false && !is_array($user_ids) && $opts === false && $forum_ids === false)
		{
			$hold_ary = array($user_ids => $this->acl_provider->acl_raw_data_single_user($user_ids));
		}
		else
		{
			$hold_ary = $this->acl_provider->acl_raw_data($user_ids, $opts, $forum_ids);
		}

		$auth_ary = array();
		foreach ($hold_ary as $user_id => $forum_ary)
		{
			foreach ($forum_ary as $forum_id => $auth_option_ary)
			{
				foreach ($auth_option_ary as $auth_option => $auth_setting)
				{
					if ($auth_setting)
					{
						$auth_ary[$forum_id][$auth_option][] = $user_id;
					}
				}
			}
		}

		return $auth_ary;
	}
}
