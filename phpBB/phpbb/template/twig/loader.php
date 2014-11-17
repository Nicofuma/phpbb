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
 * Twig Template loader
 */
class loader extends \Twig_Loader_Filesystem
{
	/**
	 * The previous template used.
	 *
	 * @var string
	 */
	private $previous_namespace = self::MAIN_NAMESPACE;

	/**
	 * The name of the style used to load the previous template.
	 *
	 * @var string
	 */
	private $previous_style;

	/**
	 * Maps the templates.
	 *
	 * @var array
	 */
	private $mapping = array();

	/**
	 * Associate a style and its paths.
	 *
	 * @var array
	 */
	private $style_paths = array();

	/**
	 * Names already checked during the current template resolution.
	 * @var array
	 */
	private $checked = array();

	/**
	 * {@inheritdoc}
	 */
	protected function findTemplate($name)
	{
		$name = (string) $name;

		// normalize name
		$name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

		if (isset($this->cache[$this->previous_style . '/'. $name])) {
			return $this->cache[$this->previous_style . '/'. $name];
		}

		$this->validateName($name);
		list($namespace, $name) = $this->get_namespace_name($name);

		try
		{
			$previous_style = $this->previous_style;
			$template_path = $this->find_template_in_namespace($namespace, $name);
			$this->previous_namespace = $namespace;

			$this->cache[$previous_style . '/'. $name] = $template_path;
			return $template_path;
		}
		catch (\Twig_Error_Loader $e)
		{
			// Tweak to allow the usage of the global namespace in a file loaded by a specific namespace
			// (eg. 'INCLUDECSS ../theme/my_file.css' in an event).
			if ($namespace === self::MAIN_NAMESPACE)
			{
				return $this->find_template_in_namespace($this->previous_namespace, $name);
			}
			else
			{
				throw $e;
			}
		}
	}

	/**
	 * Get the namespace and the real name of a template.
	 *
	 * @param string	$name	Original name.
	 * @return array	($namespace, $name)
	 * @throws \Twig_Error_Loader
	 */
	protected function get_namespace_name($name)
	{
		$namespace = self::MAIN_NAMESPACE;
		if (isset($name[0]) && '@' == $name[0]) {
			if (false === $pos = strpos($name, '/')) {
				throw new \Twig_Error_Loader(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
			}

			$namespace = substr($name, 1, $pos - 1);

			$name = substr($name, $pos + 1);
		}

		return array($namespace, $name);
	}

	/**
	 * Find a template in a given namespace.
	 *
	 * @param string $namespace		The namespace where to look.
	 * @param string $template_name	The the name of the template to search
	 * @return string
	 * @throws \Twig_Error_Loader
	 */
	protected function find_template_in_namespace($namespace, $template_name)
	{
		if (!isset($this->paths[$namespace]))
		{
			throw new \Twig_Error_Loader(sprintf('There are no registered paths for namespace "%s".', $namespace));
		}

		$path = $this->resolve_name($namespace, $template_name);
		if ($path)
		{
			return $path;
		}

		throw new \Twig_Error_Loader(sprintf('Unable to find template "%s" (looked into: %s).', $template_name, implode(', ', $this->paths[$namespace])));
	}

	/**
	 * Check whether a template exists in a namespace and returns its path if yes.
	 *
	 * @param string $namespace		The namespace where to look.
	 * @param string $template_name	The the name of the template to search
	 * @return string|null
	 */
	protected function template_exists($namespace, $template_name)
	{
		foreach ($this->paths[$namespace] as $path)
		{
			if (is_file($path . '/' . $template_name))
			{
				$this->previous_style = $this->style_paths[$path];
				return $path . '/' . $template_name;
			}
		}

		return null;
	}

	/**
	 * Set the mapping for a given namespace.
	 *
	 * @param string $namespace	The namespace to map
	 * @param array $mapping	The mapping
	 */
	public function set_mapping($namespace, $mapping)
	{
		$this->mapping[$namespace] = $mapping;
	}

	/**
	 * Return the mapping for a given namespace.
	 *
	 * @param string $namespace	The mapped namespace
	 * @return array
	 */
	public function get_mapping($namespace)
	{
		return isset($this->mapping[$namespace]) ? $this->mapping[$namespace] : array();
	}

	/**
	 * Set the paths associated with a style.
	 *
	 * @param string	$style	The style name
	 * @param array		$paths	The paths of the style
	 */
	public function set_style_paths($style, $paths)
	{
		$paths = (array) $paths;
		foreach ($paths as $path)
		{
			$this->style_paths[rtrim($path, '/\\')] = $style;
		}
	}

	/**
	 * Return the paths associated with a given style.
	 *
	 * @param string $style The style name
	 * @return array
	 */
	public function get_style_paths($style)
	{
		return isset($this->style_paths[$style]) ? $this->style_paths[$style] : array();
	}

	/**
	 * Return the name of the style holding the current template.
	 *
	 * @return string
	 */
	public function get_current_style()
	{
		if ($this->previous_style !== null)
		{
			return $this->previous_style;
		}

		return $this->style_paths[$this->paths[self::MAIN_NAMESPACE][0]];
	}

	/**
	 * Resolve a template name following the given namespace mapping. If their isn't any mapped template, return null.
	 *
	 * <pre>
	 *       +----<----No---<---------<----------<------+
	 *       |                                          |
	 * +----------+       /--------------\      /--------------\
	 * | resolver |------>| US response? |-Yes->| File exists? |-Yes----> Return the template path
	 * +----------+       \--------------/      \--------------/
	 * | only US  |              |
	 * +----------+              |
	 *   |   |                   |
	 *   |   +----<----No---<----+
	 *   |
	 *   +-->---Error (loop)--->---------> Return null
	 * </pre>
	 *
	 * Resolver priorities:
	 * <ul>
	 *   <li>User style (including override of @vendor/template)</li>
	 *   <li>$namespace (current style if $namespace is the main namespace)</li>
	 * </ul>
	 *
	 * @param string	$namespace			The namespace to use
	 * @param string	$name				The template to resolve
	 * @param boolean	$only_user_style	If yes only the user style's mapping will be considered
	 * @return string|null
	 */
	protected function resolve_name($namespace, $name, $only_user_style = false)
	{
		if ($only_user_style && isset($this->checked[$namespace . '' . $name]))
		{
			$this->checked = array();
			return null;
		}

		$resolved_name = null;
		$resolved_namespace = null;
		$primary_response = false;

		if (isset($this->mapping[self::MAIN_NAMESPACE]))
		{
			if (isset($this->mapping[self::MAIN_NAMESPACE]["@{$namespace}/{$name}"]))
			{
				list($resolved_namespace, $resolved_name) = $this->get_namespace_name($this->mapping[self::MAIN_NAMESPACE]["@{$namespace}/{$name}"]);
				$primary_response = true;
			}
			else if (isset($this->mapping[self::MAIN_NAMESPACE][$name]))
			{
				list($resolved_namespace, $resolved_name) = $this->get_namespace_name($this->mapping[self::MAIN_NAMESPACE][$name]);
				$primary_response = true;
			}
		}
		else
		{
			$primary_response = true;
		}

		if ($resolved_name === null)
		{
			if ($namespace === self::MAIN_NAMESPACE)
			{
				if (!$only_user_style
					&& $this->previous_style !== null
					&& isset($this->mapping[$this->previous_style])
					&& isset($this->mapping[$this->previous_style][$name])
				)
				{
					list($resolved_namespace, $resolved_name) = $this->get_namespace_name($this->mapping[$this->previous_style][$name]);
					$resolved_namespace = $resolved_namespace === self::MAIN_NAMESPACE ? $this->previous_style : $resolved_namespace;
				}
				else
				{
					$resolved_name      = $name;
					$resolved_namespace = $namespace;
				}
			}
			else
			{
				if (!$only_user_style
					&& isset($this->mapping[$namespace])
					&& isset($this->mapping[$namespace][$name])
				)
				{
					list($resolved_namespace, $resolved_name) = $this->get_namespace_name($this->mapping[$namespace][$name]);
					$resolved_namespace = $resolved_namespace === self::MAIN_NAMESPACE ? $namespace : $resolved_namespace;
				}
				else
				{
					$resolved_name      = $name;
					$resolved_namespace = $namespace;
				}
			}
		}

		if ($primary_response || $only_user_style)
		{
			$this->checked[$resolved_namespace . '' . $resolved_name] = true;
			$template_path = $this->template_exists($resolved_namespace, $resolved_name);
			if ($template_path !== null)
			{
				$this->checked = array();
				return $template_path;
			}
			else
			{
				return $this->resolve_name($resolved_namespace, $resolved_name, false);
			}
		}
		else
		{
			return $this->resolve_name($resolved_namespace, $resolved_name, true);
		}
	}
}
