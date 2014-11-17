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

/**
 * Twig Template class.
 */
class twig extends \phpbb\template\base
{
	/**
	 * Path of the cache directory for the template
	 *
	 * Cannot be changed during runtime.
	 *
	 * @var string
	 */
	private $cache_path = '';

	/**
	 * phpBB path helper
	 * @var \phpbb\path_helper
	 */
	protected $path_helper;

	/**
	 * phpBB root path
	 * @var string
	 */
	protected $phpbb_root_path;

	/**
	 * PHP file extension
	 * @var string
	 */
	protected $php_ext;

	/**
	 * phpBB config instance
	 * @var \phpbb\config\config
	 */
	protected $config;

	/**
	 * Current user
	 * @var \phpbb\user
	 */
	protected $user;

	/**
	 * Extension manager.
	 *
	 * @var \phpbb\extension\manager
	 */
	protected $extension_manager;

	/**
	 * Twig Environment
	 *
	 * @var environment
	 */
	protected $twig;

	/**
	 * Cache driver.
	 *
	 * @var \phpbb\cache\driver\driver_interface
	 */
	protected $cache;

	/**
	 * phpBB Twig template loader
	 *
	 * @var loader
	 */
	protected $phpbb_loader;

	/**
	 * DB driver
	 *
	 * @var \phpbb\db\driver\driver_interface
	 */
	protected $db;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\path_helper					$path_helper
	 * @param \phpbb\config\config					$config
	 * @param \phpbb\user							$user
	 * @param \phpbb\template\context				$context template context
	 * @param environment							$twig_environment
	 * @param loader								$phpbb_loader
	 * @param \phpbb\cache\driver\driver_interface	$cache
	 * @param string								$cache_path
	 * @param \phpbb\extension\manager				$extension_manager	Extension manager, if null then template events will not be invoked
	 */
	public function __construct(\phpbb\path_helper $path_helper, $config, $user, \phpbb\template\context $context, environment $twig_environment, loader $phpbb_loader, $cache_path, \phpbb\extension\manager $extension_manager = null, \phpbb\cache\driver\driver_interface $cache = null, \phpbb\db\driver\driver_interface $db = null)
	{
		$this->path_helper = $path_helper;
		$this->phpbb_root_path = $path_helper->get_phpbb_root_path();
		$this->php_ext = $path_helper->get_php_ext();
		$this->config = $config;
		$this->user = $user;
		$this->context = $context;
		$this->extension_manager = $extension_manager;
		$this->cache_path = $cache_path;
		$this->twig = $twig_environment;
		$this->phpbb_loader = $phpbb_loader;
		$this->cache = $cache;
		$this->db = $db;
	}

	/**
	 * Clear the cache
	 *
	 * @return \phpbb\template\template
	 */
	public function clear_cache()
	{
		if (is_dir($this->cache_path))
		{
			$this->twig->clearCacheFiles();
		}

		return $this;
	}

	/**
	 * Get the style tree of the style preferred by the current user
	 *
	 * @return array Style tree, most specific first
	 */
	public function get_user_style()
	{
		$style_list = array(
			$this->user->style['style_path'],
		);

		if ($this->user->style['style_parent_id'])
		{
			$style_list = array_merge($style_list, array_reverse(explode('/', $this->user->style['style_parent_tree'])));
		}

		return $style_list;
	}

	/**
	 * Set style location based on (current) user's chosen style.
	 *
	 * @param array $style_directories The directories to add style paths for
	 * 	E.g. array('ext/foo/bar/styles', 'styles')
	 * 	Default: array('styles') (phpBB's style directory)
	 * @return \phpbb\template\template $this
	 */
	public function set_style($style_directories = array())
	{
		$key = array_search('styles', $style_directories);
		if ($key !== false)
		{
			$this->phpbb_loader->setPaths(array(), 'core');
			unset($style_directories[$key]);
		}

		$styles = $this->get_styles();
		$styles[] = array('prosilver3');
		$this->set_styles($styles);

		$user_style = $this->get_user_style();
		if ($this->phpbb_loader->getPaths('core') === array())
		{
			// We should set up the core styles path since not already setup
			$this->set_core_style($user_style, true);
		}

		$this->set_extensions_style($user_style);

		$this->set_global_namespace('core', false);

		return $this;
	}

	/**
	 * Set custom style location (able to use directory outside of phpBB).
	 *
	 * Note: Templates are still compiled to phpBB's cache directory.
	 *
	 * @param string|array $names Array of names (or detailed names) or string of name of template(s) in inheritance tree order, used by extensions.
	 *	E.g. array(
	 *			'name' 		=> 'adm',
	 *			'ext_path' 	=> 'adm/style/',
	 *		)
	 * @param string|array of string $paths Array of style paths, relative to current root directory
	 * @return \phpbb\template\template $this
	 */
	public function set_custom_style($names, $paths)
	{
		$paths = (is_string($paths)) ? array($paths) : $paths;
		$names = (is_string($names)) ? array($names) : $names;

		// Set as __main__ namespace
		$this->phpbb_loader->setPaths($paths);

		if (is_array($names) && !isset($names['ext_path']))
		{
			$names = $names[0];
		}

		if (is_array($names))
		{
			$this->set_extensions_style($names['ext_path'], true);
		}
		else
		{
			$this->set_extensions_style(array($names), true);
		}

		return $this;
	}

	/**
	 * Set the paths for the global namespace.
	 *
	 * @param string	$namespace	The name of the namespace to use.
	 * @param boolean	$add_core	If true, the paths of the 'core' namespace will be added.
	 * @return \phpbb\template\template
	 *
	 * @throws \Twig_Error_Loader
	 */
	public function set_global_namespace($namespace, $add_core = false)
	{
		$namespace = str_replace('/', '_', $namespace);

		$paths = $this->phpbb_loader->getPaths($namespace);

		if ($paths === array())
		{
			// TODO: Exception or fallback to 'core'?
			throw new \Twig_Error_Loader(sprintf('The "%s" namespace does not exist.', $namespace));
		}

		if ($add_core && $namespace !== 'core')
		{
			$paths = array_merge($paths, $this->phpbb_loader->getPaths('core'));
		}

		$this->phpbb_loader->setPaths($paths);

		$this->set_loader_mapping($namespace, \Twig_Loader_Filesystem::MAIN_NAMESPACE);

		return $this;
	}

	/**
	 * Set the core style location based on (current) users's chosen style.
	 *
	 * It defines one namespace per style with its parents and 'core'' with the default style and its parents.
	 *
	 * @param array		$styles	Inheritance list of style (more specific first).
	 * @param boolean	$reset	Reset the previous paths if true.
	 * @return \phpbb\template\template
	 */
	public function set_core_style($styles, $reset = false)
	{
		//$paths = $this->get_style_inheritance_paths($this->phpbb_root_path . 'styles', $styles, true);

		$paths['core'] = $this->phpbb_loader->getPaths($styles[0]);

		$this->set_loader_mapping($styles[0], 'core');

		$this->set_loader_paths($paths, $reset);

		return $this;
	}

	/**
	 * Set the list of available styles.
	 *
	 * @param array $styles List of available styles.
	 */
	public function set_styles($styles)
	{
		foreach ($styles as $style)
		{
			$paths = $this->get_style_inheritance_paths($this->phpbb_root_path . 'styles', $style, true);
			$this->set_loader_paths($paths, true);
		}
	}

	/**
	 * Set the core style location based on (current) users's chosen style.
	 *
	 * It defines one namespace per style with its parents and 'core'' with the default style and its parents.
	 *
	 * @param array|string	$styles	Inheritance list of style (more specific first). Or a path to a directory.
	 * @param boolean		$reset	Reset the previous paths if true.
	 * @return \phpbb\template\template
	 */
	public function set_extensions_style($styles, $reset = false)
	{
		if ($this->extension_manager instanceof \phpbb\extension\manager)
		{
			$current_style = $styles[0];

			$paths = array();
			foreach ($this->extension_manager->all_enabled() as $ext_namespace => $ext_path)
			{
				$namespace = str_replace('/', '_', $ext_namespace);

				if (is_array($styles))
				{
					$style_ext_dirs = array();
					$ext_style_dirs = array();
					foreach ($styles as $style)
					{
						$style_ext_paths = $this->get_style_path("{$this->phpbb_root_path}styles/{$style}/ext/$ext_namespace");
						$ext_style_paths = $this->get_style_path("{$ext_path}styles/{$style}");
						$style_ext_dirs = array_merge($style_ext_dirs, $style_ext_paths);
						$ext_style_dirs = array_merge($ext_style_dirs, $ext_style_paths);

						$this->set_style_paths($style, $style_ext_paths);
						$this->set_style_paths($style, $ext_style_paths);
					}

					$all_style_paths = $this->get_style_path("{$ext_path}styles/all");
					$this->set_style_paths($current_style, $all_style_paths);

					$paths[$namespace] = array_merge(
					// First look in /styles/<style>/ext/<vendor>/<ext_name>/
						$style_ext_dirs,

						// Then in /ext/<vendor>/<ext_name>/styles/<style>/
						$ext_style_dirs,

						// /ext/<vendor>/<ext_name>/styles/all/
						$all_style_paths,

						// And finally in /styles/<style/
						$this->phpbb_loader->getPaths($current_style)
					);

					$this->set_loader_mapping($current_style, $namespace);
				}
				else
				{
					$paths[$namespace] = $this->get_style_path("{$ext_path}{$styles}", '');
				}
			}

			$this->set_loader_paths($paths, $reset);
		}

		return $this;
	}

	/**
	 * Get a list of valid paths for each style in a list of inherited styles.
	 *
	 * @param string	$root_dir		The directory where the styles are located.
	 * @param array		$styles			The list of styles (more specific first).
	 * @param boolean	$set_mapping	Set the loader mapping if true.
	 * @return mixed
	 */
	protected function get_style_inheritance_paths($root_dir, $styles, $set_mapping = false)
	{
		$styles = array_reverse($styles);

		$paths = array();
		$paths_parent = array();
		$parents_root_dir = array();
		foreach ($styles as $name)
		{
			$path = trim($root_dir, '/') . "/{$name}/";
			if (is_dir($path))
			{
				$parents_root_dir[] = $path;
				$style_paths = $this->get_style_path($path);
				$paths_parent = array_merge($style_paths, $paths_parent);

				$this->set_style_paths($name, $style_paths);

				if ($set_mapping)
				{
					$this->set_loader_mapping($name, $name, $parents_root_dir, $style_paths);
				}
			}

			$paths[$name] = $paths_parent;
		}

		return $paths;
	}

	/**
	 * Get an array of valid paths given a root directory and a list of directories to check.
	 *
	 * @param string		$root_dir		The root directory.
	 * @param array|string	$directories	Array of directories to check.
	 * @return array
	 */
	protected function get_style_path($root_dir, $directories = array('template', 'theme', ''))
	{
		$directories = (array) $directories;

		$paths = array();
		foreach ($directories as $dir)
		{
			$path = trim($root_dir, '/') . '/' . $dir;
			if (is_dir($path))
			{
				$paths[] = $path;
			}
		}

		return $paths;
	}

	/**
	 * Set the loader paths.
	 *
	 * @param array		$paths	Paths to set.
	 * @param boolean	$reset	Reset the core paths if true.
	 */
	protected function set_loader_paths($paths, $reset)
	{
		foreach($paths as $namespace => $namespace_paths)
		{
			if ($reset)
			{
				$this->phpbb_loader->setPaths($namespace_paths, $namespace);
			}
			else
			{
				foreach ($namespace_paths as $path)
				{
					$this->phpbb_loader->addPath($path, $namespace);
				}
			}
		}
	}

	/**
	 * Associate a list of paths to a style.
	 *
	 * @param string		$style	Name of the style.
	 * @param array|string	$paths	List of paths directly associated with this style.
	 */
	protected function set_style_paths($style, $paths)
	{
		$this->phpbb_loader->set_style_paths($style, $paths);
	}

	/**
	 * Set the loader mapping.
	 *
	 * @param string		$real_namespace		The namespace which defines the mapping (can't be __main__).
	 * @param string		$usage_namespace	The namespace where the mapping will be used (can be __main__).
	 * @param string|array	$root_dirs			List of directories where the mapping files are stored (more specific last).
	 */
	protected function set_loader_mapping($real_namespace, $usage_namespace = null, $root_dirs = null)
	{
		$usage_namespace = ($usage_namespace === null) ? $real_namespace : $usage_namespace;

		if ($usage_namespace !== $real_namespace)
		{
			$mapping = $this->phpbb_loader->get_mapping($real_namespace);
		}
		else
		{
			if (!$this->twig->isDebug() && $this->cache !== null)
			{
				$mapping = $this->cache->get('template_mapping_' . $real_namespace);
			}
			else
			{
				$mapping = false;
			}

			if ($this->twig->isDebug() || ($mapping === false && $root_dirs !== null))
			{
				$mapping   = array();
				$root_dirs = (array) $root_dirs;
				foreach ($root_dirs as $root_dir)
				{
					$file_path = $root_dir . '/template.json';
					if (is_file($file_path))
					{
						$json_data   = file_get_contents($file_path);
						$mapping_raw = json_decode($json_data, true, 2);

						if ($mapping_raw !== null)
						{
							$mapping = array_merge($mapping, $mapping_raw);
						}
					}
				}

				if (!$this->twig->isDebug() && $this->cache !== null)
				{
					$this->cache->put('template_mapping_' . $real_namespace, $mapping);
				}
			}
		}

		if ($mapping !== false && !empty($mapping))
		{
			$this->phpbb_loader->set_mapping($usage_namespace, $mapping);
		}
	}

	/**
	 * Lists all styles
	 *
	 * @return array LIst of the installed styles with their inheritance tree (more specific first).
	 */
	protected function get_styles()
	{
		$sql = 'SELECT *
			FROM ' . STYLES_TABLE;
		$result = $this->db->sql_query($sql);

		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$styles = array();
		foreach ($rows as $row)
		{
			$style_list = array(
				$row['style_path'],
			);

			if ($row['style_parent_id'])
			{
				$style_list = array_merge($style_list, array_reverse(explode('/', $row['style_parent_tree'])));
			}

			$styles[] = $style_list;
		}

		return $styles;
	}

	/**
	 * Display a template for provided handle.
	 *
	 * The template will be loaded and compiled, if necessary, first.
	 *
	 * This function calls hooks.
	 *
	 * @param string $handle Handle to display
	 * @return \phpbb\template\template $this
	 */
	public function display($handle)
	{
		$result = $this->call_hook($handle, __FUNCTION__);
		if ($result !== false)
		{
			return $result[0];
		}

		$this->twig->display($this->get_filename_from_handle($handle), $this->get_template_vars());

		return $this;
	}

	/**
	 * Display the handle and assign the output to a template variable
	 * or return the compiled result.
	 *
	 * @param string $handle Handle to operate on
	 * @param string $template_var Template variable to assign compiled handle to
	 * @param bool $return_content If true return compiled handle, otherwise assign to $template_var
	 * @return \phpbb\template\template|string if $return_content is true return string of the compiled handle, otherwise return $this
	 */
	public function assign_display($handle, $template_var = '', $return_content = true)
	{
		if ($return_content)
		{
			return $this->twig->render($this->get_filename_from_handle($handle), $this->get_template_vars());
		}

		$this->assign_var($template_var, $this->twig->render($this->get_filename_from_handle($handle, $this->get_template_vars())));

		return $this;
	}

	/**
	 * Get template vars in a format Twig will use (from the context)
	 *
	 * @return array
	 */
	protected function get_template_vars()
	{
		$context_vars = $this->context->get_data_ref();

		$vars = array_merge(
			$context_vars['.'][0], // To get normal vars
			array(
				'definition'	=> new definition(),
				'user'			=> $this->user,
				'loops'			=> $context_vars, // To get loops
			)
		);

		// cleanup
		unset($vars['loops']['.']);

		return $vars;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_source_file_for_handle($handle)
	{
		return $this->twig->getLoader()->getCacheKey($this->get_filename_from_handle($handle));
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_filename_from_handle($handle)
	{
		$filename = parent::get_filename_from_handle($handle);
		return substr($filename, 0, strrpos($filename, '.'));
	}


}
