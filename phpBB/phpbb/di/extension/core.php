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

namespace phpbb\di\extension;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
* Container core extension
*/
class core extends Extension
{
	const TWIG_OPTIONS_POSITION = 6;

	/**
	 * Config path
	 * @var string
	 */
	protected $config_path;

	/**
	 * Constructor
	 *
	 * @param string $config_path Config path
	 */
	public function __construct($config_path)
	{
		$this->config_path = $config_path;
	}

	/**
	 * Loads a specific configuration.
	 *
	 * @param array            $configs   An array of configuration values
	 * @param ContainerBuilder $container A ContainerBuilder instance
	 *
	 * @throws \InvalidArgumentException When provided tag is not defined in this extension
	 */
	public function load(array $configs, ContainerBuilder $container)
	{
		$filesystem = new \phpbb\filesystem\filesystem();
		$loader = new YamlFileLoader($container, new FileLocator($filesystem->realpath($this->config_path)));
		$loader->load($container->getParameter('core.environment') . '/container/environment.yml');

		$config = $this->getConfiguration($configs, $container);
		$config = $this->processConfiguration($config, $configs);

		if ($config['require_dev_dependencies'])
		{
			if (!class_exists('Goutte\Client', true))
			{
				trigger_error(
					'Composer development dependencies have not been set up for the ' . $container->getParameter('core.environment') . ' environment yet, run ' .
					"'php ../composer.phar install --dev' from the phpBB directory to do so.",
					E_USER_ERROR
				);
			}
		}

		$this->registerTwigConfiguration($container, $config['twig']);
		$this->registerSessionConfiguration($container, $config['session']);

		// Set the debug options
		foreach ($config['debug'] as $name => $value)
		{
			$container->setParameter('debug.' . $name, $value);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfiguration(array $config, ContainerBuilder $container)
	{
		$r = new \ReflectionClass('\phpbb\di\extension\container_configuration');
		$container->addResource(new FileResource($r->getFileName()));

		return new container_configuration();
	}

	/**
	 * Returns the recommended alias to use in XML.
	 *
	 * This alias is also the mandatory prefix to use when using YAML.
	 *
	 * @return string The alias
	 */
	public function getAlias()
	{
		return 'core';
	}

	/**
	 * Loads the Twig configuration
	 *
	 * @param ContainerBuilder $container
	 * @param array $config
	 */
	private function registerTwigConfiguration(ContainerBuilder $container, array $config)
	{
		$definition = $container->getDefinition('template.twig.environment');
		$twig_environment_options = $definition->getArgument(static::TWIG_OPTIONS_POSITION);
		if ($config['debug'])
		{
			$twig_environment_options['debug'] = true;
		}
		
		if ($config['auto_reload'])
		{
			$twig_environment_options['auto_reload'] = true;
		}

		// Replace the argument holding the options passed to the environment
		$definition->replaceArgument(static::TWIG_OPTIONS_POSITION, $twig_environment_options);

		if ($config['enable_debug_extension'])
		{
			$definition = $container->getDefinition('template.twig.extensions.debug');
			$definition->addTag('twig.extension');
		}
	}

	/**
	 * Loads the session configuration.
	 *
	 * @param ContainerBuilder $container A ContainerBuilder instance
	 * @param array $config A session configuration array
	 */
	private function registerSessionConfiguration( ContainerBuilder $container, array $config)
	{
		// session storage
		$container->setAlias('session.storage', $config['storage_id']);
		$options = array();
		foreach (array('name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor') as $key)
		{
			if (isset($config[$key]))
			{
				$options[$key] = $config[$key];
			}
		}

		if (isset($options['name']))
		{
			$container->setParameter('session.cookie_name', $options['name']);
		}

		$container->setParameter('session.storage.options', $options);

		// session handler (the internal callback registered with PHP session management)
		if (null === $config['handler_id'])
		{
			// Set the handler class to be null
			$container->getDefinition('session.storage.native')->replaceArgument(1, null);
		}
		else
		{
			$handlerId = $config['handler_id'];

			if ($config['metadata_update_threshold'] > 0)
			{
				$container->getDefinition('session.handler.write_check')->addArgument(new Reference($handlerId));
				$handlerId = 'session.handler.write_check';
			}

			$container->setAlias('session.handler', $handlerId);
		}

		$container->setParameter('session.save_path', $config['save_path']);
		$container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
	}
}
