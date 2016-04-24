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

namespace phpbb\security\user;

use phpbb\filesystem\filesystem;
use phpbb\request\request_interface;
use Symfony\Component\HttpFoundation\Request;

class session_helper
{
	/** @var filesystem */
	private $filesystem;

	/** @var request_interface */
	private $request;

	/** @var Request */
	private $symfony_request;

	/** @var string */
	private $root_path;

	/** @var array */
	private $page_array;

	/**
	 * session_helper constructor.
	 *
	 * @param request_interface $request
	 * @param Request $symfony_request
	 * @param filesystem $filesystem
	 * @param string $root_path
	 */
	public function __construct(request_interface $request, Request $symfony_request, filesystem $filesystem, $root_path)
	{
		$this->filesystem = $filesystem;
		$this->request = $request;
		$this->symfony_request = $symfony_request;
		$this->root_path = $root_path;
	}
	/**
	 * Extract current session page
	 *
	 * @return array
	 */
	public function get_current_page()
	{
		if ($this->page_array === null)
		{
			$page_array = [];

			// First of all, get the request uri...
			$script_name = $this->request->escape($this->symfony_request->getScriptName(), true);
			$args = $this->request->escape(explode('&', $this->symfony_request->getQueryString()), true);

			// If we are unable to get the script name we use REQUEST_URI as a failover and note it within the page array for easier support...
			if (!$script_name)
			{
				$script_name = htmlspecialchars_decode($this->request->server('REQUEST_URI'));
				$script_name = (($pos = strpos($script_name, '?')) !== false) ? substr($script_name, 0, $pos) : $script_name;
				$page_array['failover'] = 1;
			}

			// Replace backslashes and doubled slashes (could happen on some proxy setups)
			$script_name = str_replace(['\\', '//'], '/', $script_name);

			// Now, remove the sid and let us get a clean query string...
			$use_args = [];

			// Since some browser do not encode correctly we need to do this with some "special" characters...
			// " -> %22, ' => %27, < -> %3C, > -> %3E
			$find = ['"', "'", '<', '>', '&quot;', '&lt;', '&gt;'];
			$replace = ['%22', '%27', '%3C', '%3E', '%22', '%3C', '%3E'];

			foreach ($args as $key => $argument)
			{
				if (strpos($argument, 'sid=') === 0)
				{
					continue;
				}

				$use_args[] = str_replace($find, $replace, $argument);
			}
			unset($args);

			// The following examples given are for an request uri of {path to the phpbb directory}/adm/index.php?i=10&b=2

			// The current query string
			$query_string = trim(implode('&', $use_args));

			// basenamed page name (for example: index.php)
			$page_name = (substr($script_name, -1, 1) == '/') ? '' : basename($script_name);
			$page_name = urlencode(htmlspecialchars($page_name));

			$symfony_request_path = $this->filesystem->clean_path($this->symfony_request->getPathInfo());
			if ($symfony_request_path !== '/')
			{
				$page_name .= str_replace('%2F', '/', urlencode($symfony_request_path));
			}

			// current directory within the phpBB root (for example: adm)
			$root_dirs = explode('/', str_replace('\\', '/', $this->filesystem->realpath($this->root_path)));
			$page_dirs = explode('/', str_replace('\\', '/', $this->filesystem->realpath('./')));
			$intersection = array_intersect_assoc($root_dirs, $page_dirs);

			$root_dirs = array_diff_assoc($root_dirs, $intersection);
			$page_dirs = array_diff_assoc($page_dirs, $intersection);

			$page_dir = str_repeat('../', sizeof($root_dirs)) . implode('/', $page_dirs);

			if ($page_dir && substr($page_dir, -1, 1) == '/')
			{
				$page_dir = substr($page_dir, 0, -1);
			}

			// Current page from phpBB root (for example: adm/index.php?i=10&b=2)
			$page = (($page_dir) ? $page_dir . '/' : '') . $page_name;
			if ($query_string)
			{
				$page .= '?' . $query_string;
			}

			// The script path from the webroot to the current directory (for example: /phpBB3/adm/) : always prefixed with / and ends in /
			$script_path = $this->symfony_request->getBasePath();

			// The script path from the webroot to the phpBB root (for example: /phpBB3/)
			$script_dirs = explode('/', $script_path);
			array_splice($script_dirs, -sizeof($page_dirs));
			$root_script_path = implode('/', $script_dirs) . (sizeof($root_dirs) ? '/' . implode('/', $root_dirs) : '');

			// We are on the base level (phpBB root == webroot), lets adjust the variables a bit...
			if (!$root_script_path)
			{
				$root_script_path = ($page_dir) ? str_replace($page_dir, '', $script_path) : $script_path;
			}

			$script_path .= (substr($script_path, -1, 1) == '/') ? '' : '/';
			$root_script_path .= (substr($root_script_path, -1, 1) == '/') ? '' : '/';

			$forum_id = $this->request->variable('f', 0);
			// maximum forum id value is maximum value of mediumint unsigned column
			$forum_id = ($forum_id > 0 && $forum_id < 16777215) ? $forum_id : 0;

			$page_array += [
				'page_name'			=> $page_name,
				'page_dir'			=> $page_dir,

				'query_string'		=> $query_string,
				'script_path'		=> str_replace(' ', '%20', htmlspecialchars($script_path)),
				'root_script_path'	=> str_replace(' ', '%20', htmlspecialchars($root_script_path)),

				'page'				=> $page,
				'forum'				=> $forum_id,
			];

			$this->page_array = $page_array;
		}
		return $this->page_array;
	}
}
