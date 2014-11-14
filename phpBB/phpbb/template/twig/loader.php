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
	private $previous_namespace = self::MAIN_NAMESPACE;

	/**
	 * Maps the templates.
	 *
	 * @var array
	 */
	private $mapping = array();

	/**
	 * {@inheritdoc}
	 */
	protected function findTemplate($name)
	{
		$name = (string) $name;

		// normalize name
		$name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

		if (isset($this->cache[$name])) {
			return $this->cache[$name];
		}

		$this->validateName($name);

		$namespace = self::MAIN_NAMESPACE;
		if (isset($name[0]) && '@' == $name[0]) {
			if (false === $pos = strpos($name, '/')) {
				throw new \Twig_Error_Loader(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
			}

			$namespace = substr($name, 1, $pos - 1);

			$name = substr($name, $pos + 1);
		}

		try
		{
			$template_path = $this->find_template_in_namespace($namespace, $name);
			$this->previous_namespace = $namespace;

			$this->cache[$name] = $template_path;
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

		$resolved_name = $this->resolve_name($namespace, $template_name);

		$names = $resolved_name === null ? array($template_name) : array($resolved_name, $template_name);
		foreach ($names as $name)
		{
			foreach ($this->paths[$namespace] as $path)
			{
				if (is_file($path . '/' . $name))
				{
					return $path . '/' . $name;
				}
			}
		}

		throw new \Twig_Error_Loader(sprintf('Unable to find template "%s" (looked into: %s).', $template_name, implode(', ', $this->paths[$namespace])));
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
	 * Resolve a template name following the given namespace mapping. If their isn't any mapped template, return null.
	 *
	 * @param string $namespace	The namespace to use
	 * @param string $name		The template to resolve
	 * @return string|null
	 */
	protected function resolve_name($namespace, $name)
	{
		if (isset($this->mapping[self::MAIN_NAMESPACE]))
		{
			if (isset($this->mapping[self::MAIN_NAMESPACE]["@{$namespace}/{$name}"]))
			{
				return $this->mapping[self::MAIN_NAMESPACE]["@{$namespace}/{$name}"];
			}
		}

		if (isset($this->mapping[$namespace]))
		{
			if (isset($this->mapping[$namespace][$name]))
			{
				return $this->mapping[$namespace][$name];
			}
		}

		return null;
	}
}
