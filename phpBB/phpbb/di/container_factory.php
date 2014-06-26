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

namespace phpbb\di;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

class container_factory
{
	/**
	* @var ContainerInterface
	*/
	protected $container = null;

	/**
	* @var \phpbb\db\driver\driver_interface
	*/
	protected $dbal_connection = null;
	protected $installed_exts = null;

	protected $phpbb_root_path;
	protected $php_ext;

	protected $config_loaded = false;

	protected $cache_driver_class;
	protected $dbal_driver_class;
	protected $dbhost;
	protected $dbuser;
	protected $dbpasswd;
	protected $dbname;
	protected $dbport;
	protected $db_new_link;
	protected $table_prefix;
	protected $phpbb_adm_relative_path;

	public function get_container($phpbb_root_path, $php_ext, $inject_config = true, $use_extensions = true, $config_path = null, $use_custom_pass = true, $dump_container = true)
	{
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$container_filename = $this->get_container_filename();
		if (!defined('DEBUG_CONTAINER') && $dump_container && file_exists($container_filename))
		{
			require($container_filename);
			$this->container = new \phpbb_cache_container();
		}
		else
		{
			if ($config_path === null)
			{
				$config_path = $phpbb_root_path . 'config';
			}
			$container_extensions = array(new \phpbb\di\extension\core($config_path));

			if ($use_extensions)
			{
				$installed_exts = $this->get_installed_extensions();
				$container_extensions[] = new \phpbb\di\extension\ext($installed_exts);
			}

			$this->container = $this->create_container($container_extensions);

			if ($use_custom_pass)
			{
				$this->container->addCompilerPass(new \phpbb\di\pass\collection_pass());
				$this->container->addCompilerPass(new \phpbb\di\pass\kernel_pass());
			}

			$this->container->setParameter('core.root_path', $this->phpbb_root_path);
			$this->container->setParameter('core.php_ext', $this->php_ext);

			$this->inject_config();

			$this->container->compile();

			if ($dump_container && defined('DEBUG'))
			{
				$this->dump_container($container_filename);
			}
		}

		// Impossible because we have a compiled (and so frozen) container
		/*if ($inject_config)
		{
			$this->inject_config();
		}*/

		// Frozen container, we can't modify either the services or the parameters
		//$this->inject_dbal();

		return $this->container;
	}

	protected function dump_container($container_filename)
	{
		// Lastly, we create our cached container class
		$dumper = new PhpDumper($this->container);
		$cached_container_dump = $dumper->dump(array(
			'class'         => 'phpbb_cache_container',
			'base_class'    => 'Symfony\\Component\\DependencyInjection\\ContainerBuilder',
		));

		file_put_contents($container_filename, $cached_container_dump);
	}

	protected function inject_config()
	{
		$this->container->setParameter('core.adm_relative_path', $this->phpbb_adm_relative_path);
		$this->container->setParameter('core.table_prefix', $this->table_prefix);
		$this->container->setParameter('cache.driver.class', $this->cache_driver_class);
		$this->container->setParameter('dbal.driver.class', $this->dbal_driver_class);
		$this->container->setParameter('dbal.dbhost', $this->dbhost);
		$this->container->setParameter('dbal.dbuser', $this->dbuser);
		$this->container->setParameter('dbal.dbpasswd', $this->dbpasswd);
		$this->container->setParameter('dbal.dbname', $this->dbname);
		$this->container->setParameter('dbal.dbport', $this->dbport);
		$this->container->setParameter('dbal.new_link', $this->db_new_link);
	}

	/**
	* Inject the connection into the container if one was opened.
	*/
	protected function inject_dbal()
	{
		if ($this->dbal_connection !== null)
		{
			$this->container->set('dbal.conn', $this->dbal_connection);
		}
	}

	/**
	* Get DB connection.
	*
	* @return \phpbb\db\driver\driver_interface
	*/
	protected function get_dbal_connection()
	{
		if ($this->dbal_connection === null)
		{
			$this->load_config_file();
			$this->dbal_connection = new $this->dbal_driver_class();
			$this->dbal_connection->sql_connect($this->dbhost, $this->dbuser, $this->dbpasswd, $this->dbname, $this->dbport, $this->db_new_link);
		}

		return $this->dbal_connection;
	}

	/**
	* Get enabled extensions.
	*
	* @return array enabled extensions
	*/
	protected function get_installed_extensions()
	{
		$db = $this->get_dbal_connection();
		$extension_table = $this->table_prefix.'ext';

		$sql = 'SELECT *
			FROM ' . $extension_table . '
			WHERE ext_active = 1';

		$result = $db->sql_query($sql);
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$exts = array();
		foreach ($rows as $row)
		{
			$exts[$row['ext_name']] = $this->phpbb_root_path . 'ext/' . $row['ext_name'] . '/';
		}

		return $exts;
	}

	/**
	* Load the config file and store the information
	*/
	protected function load_config_file()
	{
		if (!$this->config_loaded)
		{
			global $acm_type, $dbms, $dbhost, $dbuser, $dbpasswd, $dbname, $dbport, $table_prefix, $phpbb_adm_relative_path;
			$this->config_loaded = true;

			// Should not happend
			if (!defined('PHPBB_INSTALLED'))
			{
				require($this->phpbb_root_path . 'config.' . $this->php_ext);
			}
			else
			{
			}

			$this->cache_driver_class = $this->convert_30_acm_type($acm_type);
			$this->dbal_driver_class = phpbb_convert_30_dbms_to_31($dbms);
			$this->dbhost = $dbhost;
			$this->dbuser = $dbuser;
			$this->dbpasswd = $dbpasswd;
			$this->dbname = $dbname;
			$this->dbport = $dbport;
			$this->db_new_link = defined('PHPBB_DB_NEW_LINK');
			$this->table_prefix = $table_prefix;
			$this->phpbb_adm_relative_path = $phpbb_adm_relative_path;
		}
	}

	/**
	* Create the ContainerBuilder object
	*
	* @param array $extensions Array of Container extension objects
	* @return ContainerBuilder object
	*/
	protected function create_container(array $extensions)
	{
		$container = new ContainerBuilder();

		foreach ($extensions as $extension)
		{
			$container->registerExtension($extension);
			$container->loadFromExtension($extension->getAlias());
		}

		return $container;
	}

	/**
	* Get the filename under which the dumped container will be stored.
	*
	* @return string Path for dumped container
	*/
	protected function get_container_filename()
	{
		$filename = str_replace(array('/', '.'), array('slash', 'dot'), $this->phpbb_root_path);
		return $this->phpbb_root_path . 'cache/container_' . $filename . '.' . $this->php_ext;
	}

	/**
	* Convert 3.0 ACM type to 3.1 cache driver class name
	*
	* @param string $acm_type ACM type
	* @return string cache driver class
	*/
	protected function convert_30_acm_type($acm_type)
	{
		if (preg_match('#^[a-z]+$#', $acm_type))
		{
			return 'phpbb\\cache\\driver\\' . $acm_type;
		}

		return $acm_type;
	}
}
