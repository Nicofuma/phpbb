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

namespace phpbb\controller;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

/**
* Controller interface
*/
class provider
{
	/**
	* YAML file(s) containing route information
	* @var array
	*/
	protected $routing_files;

	/**
	* Collection of the routes in phpBB and all found extensions
	* @var RouteCollection
	*/
	protected $routes;

	/**
	 * phpBB root path
	 * @var string
	 */
	protected $phpbb_root_path;

	/**
	* Construct method
	*
	* @param string $phpbb_root_path phpBB root path
	* @param array $routing_files Array of strings containing paths
	*							to YAML files holding route information
	*/
	public function __construct($phpbb_root_path, $routing_files = array())
	{
		$this->routing_files = $routing_files;
		$this->phpbb_root_path = $phpbb_root_path;
	}

	/**
	* Find the list of routing files
	*
	* @param array $paths Array of paths where to look for routing files.
	* @return null
	*/
	public function find_routing_files(array $paths)
	{
		$this->routing_files = array($this->phpbb_root_path . 'config/' . PHPBB_ENVIRONMENT . '/routing.yml');

		foreach ($paths as $path)
		{
			if (file_exists($path . 'config/' . PHPBB_ENVIRONMENT . '/routing.yml'))
			{
				$this->routing_files[] = $path . 'config/' . PHPBB_ENVIRONMENT . '/routing.yml';
			}
			else if (!is_dir($path . 'config/' . PHPBB_ENVIRONMENT))
			{
				if (file_exists($path . 'config/default/routing.yml'))
				{
					$this->routing_files[] = $path . 'config/default/routing.yml';
				}
				else if (!is_dir($path . 'config/default') && file_exists($path . 'config/routing.yml'))
				{
					$this->routing_files[] = $path . 'config/routing.yml';
				}
			}
		}
	}

	/**
	* Find a list of controllers
	*
	* @param string $base_path Base path to prepend to file paths
	* @return provider
	*/
	public function find($base_path = '')
	{
		$this->routes = new RouteCollection;
		foreach ($this->routing_files as $file_path)
		{
			$loader = new YamlFileLoader(new FileLocator(phpbb_realpath($base_path)));
			$this->routes->addCollection($loader->load($file_path));
		}

		return $this;
	}

	/**
	* Get the list of routes
	*
	* @return RouteCollection Get the route collection
	*/
	public function get_routes()
	{
		return $this->routes;
	}
}
