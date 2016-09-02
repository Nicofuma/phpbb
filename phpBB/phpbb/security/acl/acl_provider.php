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

use phpbb\db\driver\driver_interface as db_interface;
use phpbb\cache\driver\driver_interface as cache_interface;
use phpbb\security\user\user;

/**
 * Load the acls fro the database.
 *
 * Warning: Currently marked as internal as it has been introduced only to introduce the user object support.
 *          It will most likely change in a near future.
 *
 * @internal
 */
class acl_provider
{
	/** @var cache_interface */
	private $cache;

	/** @var db_interface */
	private $db;

	/** @var array */
	private $acl_options;

	/** @var array */
	private $role_cache;

	/** @var array */
	private $acl = [];

	/** @var array */
	private $acl_forum_ids = [];

	public function __construct(db_interface $db, cache_interface $cache)
	{
		$this->cache = $cache;
		$this->db = $db;
	}

	/**
	 * Returns the acls for a given user (load them if necessary)
	 *
	 * @param user $user
	 *
	 * @return array
	 */
	public function get_acl(user $user)
	{
		if (!array_key_exists($user->get_id(), $this->acl))
		{
			$this->load_acls($user);
		}

		return $this->acl[$user->get_id()];
	}

	/**
	 * Return a given option ids
	 *
	 * @param string $section (global or local)
	 * @param string $option
	 *
	 * @return bool
	 */
	public function get_option($section, $option)
	{
		$this->load_options();

		return $this->acl_options[$section][$option];
	}

	/**
	 * Returns true if a given option exists
	 *
	 * @param string $section (global or local)
	 * @param string $option
	 *
	 * @return bool
	 */
	public function has_option($section, $option)
	{
		$this->load_options();

		return isset($this->acl_options[$section][$option]);
	}

	/**
	 * Return the list of forums without any acls for the given user
	 * @param user $user
	 *
	 * @return array
	 */
	public function get_user_forums_id(user $user)
	{
		$this->load_acls($user);

		if (!array_key_exists($user->get_id(), $this->acl_forum_ids))
		{
			$sql = 'SELECT forum_id
					FROM ' . FORUMS_TABLE;

			if (count($this->acl[$user->get_id()]))
			{
				$sql .= ' WHERE ' . $this->db->sql_in_set('forum_id', array_keys($this->acl[$user->get_id()]), true);
			}
			$result = $this->db->sql_query($sql);

			$this->acl_forum_ids = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->acl_forum_ids[] = $row['forum_id'];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->acl_forum_ids[$user->get_id()];
	}

	/**
	 * Load the ACLs for the given user
	 *
	 * @param user $user
	 */
	private function load_acls(user $user)
	{
		$this->load_options();

		if (!trim($user->data['user_permissions']))
		{
			$this->acl_cache($user);
		}

		// Fill ACL array
		$this->_fill_acl($user);

		// Verify bitstring length with options provided...
		$renew = false;
		$global_length = count($this->acl_options['global']);
		$local_length = count($this->acl_options['local']);

		// Specify comparing length (bitstring is padded to 31 bits)
		$global_length = ($global_length % 31) ? ($global_length - ($global_length % 31) + 31) : $global_length;
		$local_length = ($local_length % 31) ? ($local_length - ($local_length % 31) + 31) : $local_length;

		// You thought we are finished now? Noooo... now compare them.
		foreach ($this->acl[$user->get_id()] as $forum_id => $bitstring)
		{
			if (($forum_id && strlen($bitstring) !== $local_length) || (!$forum_id && strlen($bitstring) !== $global_length))
			{
				$renew = true;
				break;
			}
		}

		// If a bitstring within the list does not match the options, we have a user with incorrect permissions set and need to renew them
		if ($renew)
		{
			$this->acl_cache($user);
			$this->_fill_acl($user);
		}
	}

	/**
	 * Fill ACL array with relevant bitstrings from user_permissions column
	 *
	 * @param user $user
	 */
	private function _fill_acl(user $user)
	{
		$user_permissions = $user->data['user_permissions'];
		$user_id = $user->get_id();
		$seq_cache = [];
		$this->acl[$user_id] = [];
		$user_permissions = explode("\n", $user_permissions);

		foreach ($user_permissions as $f => $seq)
		{
			if ($seq)
			{
				$i = 0;

				if (!isset($this->acl[$user_id][$f]))
				{
					$this->acl[$user_id][$f] = '';
				}

				while ($subseq = substr($seq, $i, 6))
				{
					if (isset($seq_cache[$subseq]))
					{
						$converted = $seq_cache[$subseq];
					}
					else
					{
						$converted = $seq_cache[$subseq] = str_pad(base_convert($subseq, 36, 2), 31, 0, STR_PAD_LEFT);
					}

					// We put the original bitstring into the acl array
					$this->acl[$user_id][$f] .= $converted;
					$i += 6;
				}
			}
		}
	}

	/**
	 * Loads the acl_options list
	 */
	private function load_options()
	{
		if ($this->acl_options !== null)
		{
			return;
		}

		$this->acl_options = $this->cache->get('_acl_options');

		if ($this->acl_options === false)
		{
			$sql = 'SELECT auth_option_id, auth_option, is_global, is_local
				FROM ' . ACL_OPTIONS_TABLE . '
				ORDER BY auth_option_id';
			$result = $this->db->sql_query($sql);

			$global = $local = 0;
			$this->acl_options = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['is_global'])
				{
					$this->acl_options['global'][$row['auth_option']] = $global++;
				}

				if ($row['is_local'])
				{
					$this->acl_options['local'][$row['auth_option']] = $local++;
				}

				$this->acl_options['id'][$row['auth_option']] = (int) $row['auth_option_id'];
				$this->acl_options['option'][(int) $row['auth_option_id']] = $row['auth_option'];
			}

			$this->db->sql_freeresult($result);

			$this->cache->put('_acl_options', $this->acl_options);
		}
	}

	/**
	 * Cache data to user_permissions row
	 *
	 * @param user $user
	 */
	private function acl_cache(user $user)
	{
		// Empty user_permissions
		$user->data['user_permissions'] = '';

		$hold_ary = $this->acl_raw_data_single_user($user->get_id());

		// Key 0 in $hold_ary are global options, all others are forum_ids

		// If this user is founder we're going to force fill the admin options ...
		if ((int) $user->data['user_type'] === USER_FOUNDER)
		{
			foreach ($this->acl_options['global'] as $opt => $id)
			{
				if (strpos($opt, 'a_') === 0)
				{
					$hold_ary[0][$this->acl_options['id'][$opt]] = ACL_YES;
				}
			}
		}

		$hold_str = $this->build_bitstring($hold_ary);

		if ($hold_str)
		{
			$user->data['user_permissions'] = $hold_str;

			$sql = 'UPDATE ' . USERS_TABLE . "
				SET user_permissions = '" . $this->db->sql_escape($user->data['user_permissions']) . "',
					user_perm_from = 0
				WHERE user_id = " . $user->get_id();
			$this->db->sql_query($sql);
		}
	}

	/**
	 * Get raw acl data based on user for caching user_permissions
	 * This function returns almost the same data as acl_raw_data(),
	 * but without the user id as the first key within the array
	 */
	public function acl_raw_data_single_user($user_id)
	{
		// Check if the role-cache is there
		if (($this->role_cache = $this->cache->get('_role_cache')) === false)
		{
			$this->role_cache = array();

			// We pre-fetch roles
			$sql = 'SELECT *
				FROM ' . ACL_ROLES_DATA_TABLE . '
				ORDER BY role_id ASC';
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->role_cache[$row['role_id']][$row['auth_option_id']] = (int) $row['auth_setting'];
			}
			$this->db->sql_freeresult($result);

			foreach ($this->role_cache as $role_id => $role_options)
			{
				$this->role_cache[$role_id] = serialize($role_options);
			}

			$this->cache->put('_role_cache', $this->role_cache);
		}

		$hold_ary = array();

		// Grab user-specific permission settings
		$sql = 'SELECT forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . ACL_USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			// If a role is assigned, assign all options included within this role. Else, only set this one option.
			if ($row['auth_role_id'])
			{
				$hold_ary[$row['forum_id']] = (empty($hold_ary[$row['forum_id']])) ? unserialize($this->role_cache[$row['auth_role_id']]) : $hold_ary[$row['forum_id']] + unserialize($this->role_cache[$row['auth_role_id']]);
			}
			else
			{
				$hold_ary[$row['forum_id']][$row['auth_option_id']] = $row['auth_setting'];
			}
		}
		$this->db->sql_freeresult($result);

		// Now grab group-specific permission settings
		$sql = 'SELECT a.forum_id, a.auth_option_id, a.auth_role_id, a.auth_setting
			FROM ' . ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g
			WHERE a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				AND ug.user_id = ' . $user_id;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!$row['auth_role_id'])
			{
				$hold_ary[$row['forum_id']] = $this->_set_group_hold_ary($hold_ary[$row['forum_id']], $row['auth_option_id'], $row['auth_setting']);
			}
			else if (!empty($this->role_cache[$row['auth_role_id']]))
			{
				foreach (unserialize($this->role_cache[$row['auth_role_id']]) as $option_id => $setting)
				{
					$hold_ary[$row['forum_id']] = $this->_set_group_hold_ary($hold_ary[$row['forum_id']], $option_id, $setting);
				}
			}
		}
		$this->db->sql_freeresult($result);

		return $hold_ary;
	}

	/**
	 * Private function snippet for setting a specific piece of the hold_ary
	 *
	 * @param array $hold_ary
	 * @param int $option_id
	 * @param int $setting
	 *
	 * @return array
	 */
	private function _set_group_hold_ary($hold_ary, $option_id, $setting)
	{
		if (!isset($hold_ary[$option_id]) || (isset($hold_ary[$option_id]) && $hold_ary[$option_id] != ACL_NEVER))
		{
			$hold_ary[$option_id] = $setting;

			// If we detect ACL_NEVER, we will unset the flag option (within building the bitstring it is correctly set again)
			if ($setting == ACL_NEVER)
			{
				$flag = substr($this->acl_options['option'][$option_id], 0, strpos($this->acl_options['option'][$option_id], '_') + 1);
				$flag = (int) $this->acl_options['id'][$flag];

				if (isset($hold_ary[$flag]) && $hold_ary[$flag] == ACL_YES)
				{
					unset($hold_ary[$flag]);
				}
			}
		}

		return $hold_ary;
	}

	/**
	 * Get raw acl data based on user/option/forum
	 *
	 * @param bool|int|array $user_id
	 * @param bool|string|array $opts
	 * @param bool|int|array $forum_id
	 *
	 * @return array
	 */
	public function acl_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		$sql_user = ($user_id !== false) ? ((!is_array($user_id)) ? 'user_id = ' . (int) $user_id : $this->db->sql_in_set('user_id', array_map('intval', $user_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $this->db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = $sql_opts_select = $sql_opts_from = '';
		$hold_ary = array();

		if ($opts !== false)
		{
			$sql_opts_select = ', ao.auth_option';
			$sql_opts_from = ', ' . ACL_OPTIONS_TABLE . ' ao';
			$sql_opts = $this->build_auth_option_statement('ao.auth_option', $opts);
		}

		$sql_ary = array();

		// Grab non-role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, a.auth_setting, a.auth_option_id' . $sql_opts_select . '
			FROM ' . ACL_USERS_TABLE . ' a' . $sql_opts_from . '
			WHERE a.auth_role_id = 0 ' .
		             (($sql_opts_from) ? 'AND a.auth_option_id = ao.auth_option_id ' : '') .
		             (($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		// Now the role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, r.auth_option_id, r.auth_setting, r.auth_option_id' . $sql_opts_select . '
			FROM ' . ACL_USERS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r' . $sql_opts_from . '
			WHERE a.auth_role_id = r.role_id ' .
		             (($sql_opts_from) ? 'AND r.auth_option_id = ao.auth_option_id ' : '') .
		             (($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		foreach ($sql_ary as $sql)
		{
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$option = ($sql_opts_select) ? $row['auth_option'] : $this->acl_options['option'][$row['auth_option_id']];
				$hold_ary[$row['user_id']][$row['forum_id']][$option] = $row['auth_setting'];
			}
			$this->db->sql_freeresult($result);
		}

		$sql_ary = array();

		// Now grab group settings - non-role specific...
		$sql_ary[] = 'SELECT ug.user_id, a.forum_id, a.auth_setting, a.auth_option_id' . $sql_opts_select . '
			FROM ' . ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g' . $sql_opts_from . '
			WHERE a.auth_role_id = 0 ' .
		             (($sql_opts_from) ? 'AND a.auth_option_id = ao.auth_option_id ' : '') . '
				AND a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				' . (($sql_user) ? 'AND ug.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		// Now grab group settings - role specific...
		$sql_ary[] = 'SELECT ug.user_id, a.forum_id, r.auth_setting, r.auth_option_id' . $sql_opts_select . '
			FROM ' . ACL_GROUPS_TABLE . ' a, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g, ' . ACL_ROLES_DATA_TABLE . ' r' . $sql_opts_from . '
			WHERE a.auth_role_id = r.role_id ' .
		             (($sql_opts_from) ? 'AND r.auth_option_id = ao.auth_option_id ' : '') . '
				AND a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				' . (($sql_user) ? 'AND ug.' . $sql_user : '') . "
				$sql_forum
				$sql_opts";

		foreach ($sql_ary as $sql)
		{
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$option = ($sql_opts_select) ? $row['auth_option'] : $this->acl_options['option'][$row['auth_option_id']];

				if (!isset($hold_ary[$row['user_id']][$row['forum_id']][$option]) || (isset($hold_ary[$row['user_id']][$row['forum_id']][$option]) && $hold_ary[$row['user_id']][$row['forum_id']][$option] != ACL_NEVER))
				{
					$hold_ary[$row['user_id']][$row['forum_id']][$option] = $row['auth_setting'];

					// If we detect ACL_NEVER, we will unset the flag option (within building the bitstring it is correctly set again)
					if ($row['auth_setting'] == ACL_NEVER)
					{
						$flag = substr($option, 0, strpos($option, '_') + 1);

						if (isset($hold_ary[$row['user_id']][$row['forum_id']][$flag]) && $hold_ary[$row['user_id']][$row['forum_id']][$flag] == ACL_YES)
						{
							unset($hold_ary[$row['user_id']][$row['forum_id']][$flag]);
						}
					}
				}
			}
			$this->db->sql_freeresult($result);
		}

		return $hold_ary;
	}

	/**
	 * Fill auth_option statement for later querying based on the supplied options
	 *
	 * @param string $key
	 * @param string|array $auth_options
	 *
	 * @return string
	 */
	private function build_auth_option_statement($key, $auth_options)
	{
		if (!is_array($auth_options))
		{
			if (strpos($auth_options, '%') !== false)
			{
				$sql_opts = "AND $key " . $this->db->sql_like_expression(str_replace('%', $this->db->get_any_char(), $auth_options));
			}
			else
			{
				$sql_opts = "AND $key = '" . $this->db->sql_escape($auth_options) . "'";
			}
		}
		else
		{
			$is_like_expression = false;

			foreach ($auth_options as $option)
			{
				if (strpos($option, '%') !== false)
				{
					$is_like_expression = true;
				}
			}

			if (!$is_like_expression)
			{
				$sql_opts = 'AND ' . $this->db->sql_in_set($key, $auth_options);
			}
			else
			{
				$sql = array();

				foreach ($auth_options as $option)
				{
					if (strpos($option, '%') !== false)
					{
						$sql[] = $key . ' ' . $this->db->sql_like_expression(str_replace('%', $this->db->get_any_char(), $option));
					}
					else
					{
						$sql[] = $key . " = '" . $this->db->sql_escape($option) . "'";
					}
				}

				$sql_opts = 'AND (' . implode(' OR ', $sql) . ')';
			}
		}

		return $sql_opts;
	}

	/**
	 * Build bitstring from permission set
	 *
	 * @param array $hold_ary
	 *
	 * @return string
	 */
	private function build_bitstring(array $hold_ary)
	{
		$hold_str = '';

		if (count($hold_ary))
		{
			ksort($hold_ary);

			$last_f = 0;

			foreach ($hold_ary as $f => $auth_ary)
			{
				$ary_key = (!$f) ? 'global' : 'local';

				$bitstring = [];
				foreach ($this->acl_options[$ary_key] as $opt => $id)
				{
					if (isset($auth_ary[$this->acl_options['id'][$opt]]))
					{
						$bitstring[$id] = $auth_ary[$this->acl_options['id'][$opt]];

						$option_key = substr($opt, 0, strpos($opt, '_') + 1);

						// If one option is allowed, the global permission for this option has to be allowed too
						// example: if the user has the a_ permission this means he has one or more a_* permissions
						if ($auth_ary[$this->acl_options['id'][$opt]] == ACL_YES && (!isset($bitstring[$this->acl_options[$ary_key][$option_key]]) || $bitstring[$this->acl_options[$ary_key][$option_key]] == ACL_NEVER))
						{
							$bitstring[$this->acl_options[$ary_key][$option_key]] = ACL_YES;
						}
					}
					else
					{
						$bitstring[$id] = ACL_NEVER;
					}
				}

				// Now this bitstring defines the permission setting for the current forum $f (or global setting)
				$bitstring = implode('', $bitstring);

				// The line number indicates the id, therefore we have to add empty lines for those ids not present
				$hold_str .= str_repeat("\n", $f - $last_f);

				// Convert bitstring for storage - we do not use binary/bytes because PHP's string functions are not fully binary safe
				for ($i = 0, $bit_length = strlen($bitstring); $i < $bit_length; $i += 31)
				{
					$hold_str .= str_pad(base_convert(str_pad(substr($bitstring, $i, 31), 31, 0, STR_PAD_RIGHT), 2, 36), 6, 0, STR_PAD_LEFT);
				}

				$last_f = $f;
			}

			$hold_str = rtrim($hold_str);
		}

		return $hold_str;
	}

	/**
	 * Get raw user based permission settings
	 *
	 * @param bool|int|array $user_id
	 * @param bool|string|array $opts
	 * @param bool|int|array $forum_id
	 *
	 * @return array
	 */
	public function acl_user_raw_data($user_id = false, $opts = false, $forum_id = false)
	{
		$sql_user = ($user_id !== false) ? ((!is_array($user_id)) ? 'user_id = ' . (int) $user_id : $this->db->sql_in_set('user_id', array_map('intval', $user_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $this->db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = '';
		$hold_ary = $sql_ary = array();

		if ($opts !== false)
		{
			$this->build_auth_option_statement('ao.auth_option', $opts, $sql_opts);
		}

		// Grab user settings - non-role specific...
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, a.auth_setting, a.auth_option_id, ao.auth_option
			FROM ' . ACL_USERS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = 0
				AND a.auth_option_id = ao.auth_option_id ' .
		             (($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		// Now the role settings - user-specific
		$sql_ary[] = 'SELECT a.user_id, a.forum_id, r.auth_option_id, r.auth_setting, r.auth_option_id, ao.auth_option
			FROM ' . ACL_USERS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = r.role_id
				AND r.auth_option_id = ao.auth_option_id ' .
		             (($sql_user) ? 'AND a.' . $sql_user : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		foreach ($sql_ary as $sql)
		{
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$hold_ary[$row['user_id']][$row['forum_id']][$row['auth_option']] = $row['auth_setting'];
			}
			$db->sql_freeresult($result);
		}

		return $hold_ary;
	}

	/**
	 * Get assigned roles
	 *
	 * @param string $user_type
	 * @param string $role_type
	 * @param bool|int|array $ug_id
	 * @param bool|int|array $forum_id
	 *
	 * @return array
	 */
	public function acl_role_data($user_type, $role_type, $ug_id = false, $forum_id = false)
	{
		$roles = array();

		$sql_id = ($user_type == 'user') ? 'user_id' : 'group_id';

		$sql_ug = ($ug_id !== false) ? ((!is_array($ug_id)) ? "AND a.$sql_id = $ug_id" : 'AND ' . $this->db->sql_in_set("a.$sql_id", $ug_id)) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? "AND a.forum_id = $forum_id" : 'AND ' . $this->db->sql_in_set('a.forum_id', $forum_id)) : '';

		// Grab assigned roles...
		$sql = 'SELECT a.auth_role_id, a.' . $sql_id . ', a.forum_id
			FROM ' . (($user_type == 'user') ? ACL_USERS_TABLE : ACL_GROUPS_TABLE) . ' a, ' . ACL_ROLES_TABLE . " r
			WHERE a.auth_role_id = r.role_id
				AND r.role_type = '" . $this->db->sql_escape($role_type) . "'
				$sql_ug
				$sql_forum
			ORDER BY r.role_order ASC";
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$roles[$row[$sql_id]][$row['forum_id']] = $row['auth_role_id'];
		}
		$this->db->sql_freeresult($result);

		return $roles;
	}

	/**
	 * Get raw group based permission settings
	 *
	 * @param bool|int|array $group_id
	 * @param bool|string|array $opts
	 * @param bool|int|array $forum_id
	 *
	 * @return array
	 */
	public function acl_group_raw_data($group_id = false, $opts = false, $forum_id = false)
	{
		$sql_group = ($group_id !== false) ? ((!is_array($group_id)) ? 'group_id = ' . (int) $group_id : $this->db->sql_in_set('group_id', array_map('intval', $group_id))) : '';
		$sql_forum = ($forum_id !== false) ? ((!is_array($forum_id)) ? 'AND a.forum_id = ' . (int) $forum_id : 'AND ' . $this->db->sql_in_set('a.forum_id', array_map('intval', $forum_id))) : '';

		$sql_opts = '';
		$hold_ary = $sql_ary = array();

		if ($opts !== false)
		{
			$sql_opts = $this->build_auth_option_statement('ao.auth_option', $opts);
		}

		// Grab group settings - non-role specific...
		$sql_ary[] = 'SELECT a.group_id, a.forum_id, a.auth_setting, a.auth_option_id, ao.auth_option
			FROM ' . ACL_GROUPS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = 0
				AND a.auth_option_id = ao.auth_option_id ' .
		             (($sql_group) ? 'AND a.' . $sql_group : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		// Now grab group settings - role specific...
		$sql_ary[] = 'SELECT a.group_id, a.forum_id, r.auth_setting, r.auth_option_id, ao.auth_option
			FROM ' . ACL_GROUPS_TABLE . ' a, ' . ACL_ROLES_DATA_TABLE . ' r, ' . ACL_OPTIONS_TABLE . ' ao
			WHERE a.auth_role_id = r.role_id
				AND r.auth_option_id = ao.auth_option_id ' .
		             (($sql_group) ? 'AND a.' . $sql_group : '') . "
				$sql_forum
				$sql_opts
			ORDER BY a.forum_id, ao.auth_option";

		foreach ($sql_ary as $sql)
		{
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$hold_ary[$row['group_id']][$row['forum_id']][$row['auth_option']] = $row['auth_setting'];
			}
			$this->db->sql_freeresult($result);
		}

		return $hold_ary;
	}

	/**
	 * Clear one or all users cached permission settings
	 *
	 * @param bool|int $user_id
	 */
	public function acl_clear_prefetch($user_id = false)
	{
		// Rebuild options cache
		$this->cache->destroy('_role_cache');

		$sql = 'SELECT *
			FROM ' . ACL_ROLES_DATA_TABLE . '
			ORDER BY role_id ASC';
		$result = $this->db->sql_query($sql);

		$this->role_cache = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->role_cache[$row['role_id']][$row['auth_option_id']] = (int) $row['auth_setting'];
		}
		$this->db->sql_freeresult($result);

		foreach ($this->role_cache as $role_id => $role_options)
		{
			$this->role_cache[$role_id] = serialize($role_options);
		}

		$this->cache->put('_role_cache', $this->role_cache);

		// Now empty user permissions
		$where_sql = '';

		if ($user_id !== false)
		{
			$user_id = (!is_array($user_id)) ? $user_id = array((int) $user_id) : array_map('intval', $user_id);
			$where_sql = ' WHERE ' . $this->db->sql_in_set('user_id', $user_id);
		}

		$sql = 'UPDATE ' . USERS_TABLE . "
			SET user_permissions = '',
				user_perm_from = 0
			$where_sql";
		$this->db->sql_query($sql);
	}
}
