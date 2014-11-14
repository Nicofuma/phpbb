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

namespace phpbb\template\twig;

use \Symfony\Component\DependencyInjection\ContainerInterface;

class environment extends \Twig_Environment
{
	/** @var \phpbb\config\config */
	protected $phpbb_config;

	/** @var \phpbb\path_helper */
	protected $phpbb_path_helper;

	/** @var ContainerInterface */
	protected $container;

	/** @var \phpbb\extension\manager */
	protected $extension_manager;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $web_root_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config		$phpbb_config		The phpBB configuration
	 * @param \phpbb\path_helper		$path_helper		phpBB path helper
	 * @param ContainerInterface		$container			The dependency injection container
	 * @param loader					$phpbb_loader		phpBB Twig template loader
	 * @param string					$cache_path			The path to the cache directory
	 * @param \phpbb\extension\manager	$extension_manager	phpBB extension manager
	 * @param array|\ArrayAccess		$loaders			Twig loaders
	 * @param array|\ArrayAccess		$extensions			Twig extensions
	 * @param array						$options			Array of options to pass to Twig
	 */
	public function __construct($phpbb_config, \phpbb\path_helper $path_helper, ContainerInterface $container, loader $phpbb_loader, $cache_path, \phpbb\extension\manager $extension_manager = null, $loaders = array(), $extensions = array(), $options = array())
	{
		$this->phpbb_config = $phpbb_config;

		$this->phpbb_path_helper = $path_helper;
		$this->extension_manager = $extension_manager;
		$this->container = $container;

		$this->phpbb_root_path = $this->phpbb_path_helper->get_phpbb_root_path();
		$this->web_root_path = $this->phpbb_path_helper->get_web_root_path();

		$options = array_merge(array(
			'cache'			=> (defined('IN_INSTALL')) ? false : $cache_path,
			'debug'			=> defined('DEBUG'),
			'auto_reload'	=> (bool) $this->phpbb_config['load_tplcompile'],
			'autoescape'	=> false,
		), $options);

		$chain_loader = new \Twig_Loader_Chain();
		$chain_loader->addLoader($phpbb_loader);

		foreach ($loaders as $loader)
		{
			$chain_loader->addLoader($loader);
		}

		parent::__construct($chain_loader, $options);

		foreach ($extensions as $extension)
		{
			$this->addExtension($extension);
		}
	}

	/**
	* {@inheritdoc}
	*/
	public function getLexer()
	{
		if (null === $this->lexer)
		{
			$this->lexer = $this->container->get('template.twig.lexer');
			$this->lexer->set_environment($this);
		}

		return $this->lexer;
	}

	/**
	* Get the list of enabled phpBB extensions
	*
	* Used in EVENT node
	*
	* @return array
	*/
	public function get_phpbb_extensions()
	{
		return ($this->extension_manager) ? $this->extension_manager->all_enabled() : array();
	}

	/**
	* Get phpBB config
	*
	* @return \phpbb\config\config
	*/
	public function get_phpbb_config()
	{
		return $this->phpbb_config;
	}

	/**
	* Get the phpBB root path
	*
	* @return string
	*/
	public function get_phpbb_root_path()
	{
		return $this->phpbb_root_path;
	}

	/**
	* Get the web root path
	*
	* @return string
	*/
	public function get_web_root_path()
	{
		return $this->web_root_path;
	}

	/**
	* Get the phpbb path helper object
	*
	* @return \phpbb\path_helper
	*/
	public function get_path_helper()
	{
		return $this->phpbb_path_helper;
	}

	/**
	* Finds a template by name.
	*
	* @param string  $name  The template name
	* @return string
	* @throws \Twig_Error_Loader
	*/
	public function findTemplate($name)
	{
		return parent::getLoader()->getCacheKey($name);
	}
}
