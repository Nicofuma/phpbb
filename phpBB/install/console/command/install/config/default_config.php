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

namespace phpbb\install\console\command\install\config;

use phpbb\install\exception\installer_exception;
use phpbb\install\helper\iohandler\cli_iohandler;
use phpbb\install\helper\iohandler\factory;
use phpbb\install\helper\iohandler\iohandler_interface;
use phpbb\install\installer;
use phpbb\install\installer_configuration;
use phpbb\language\language;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class default_config extends \phpbb\console\command\command
{
	/**
	 * @var factory
	 */
	protected $iohandler_factory;

	/**
	 * @var installer
	 */
	protected $installer;

	/**
	 * @var language
	 */
	protected $language;

	/**
	 * Constructor
	 *
	 * @param language $language
	 * @param factory $factory
	 * @param installer $installer
	 */
	public function __construct(language $language, factory $factory, installer $installer)
	{
		$this->iohandler_factory = $factory;
		$this->installer = $installer;
		$this->language = $language;

		parent::__construct(new \phpbb\user($language, 'datetime'));
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('install:config:default')
			->addArgument(
				'config-file',
				InputArgument::OPTIONAL,
				$this->language->lang('CLI_CONFIG_FILE'))
			->setDescription($this->language->lang('CLI_INSTALL_DEFAULT_CONFIG'))
		;
	}

	/**
	 * Display the default configuration
	 *
	 * @param InputInterface  $input  An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 *
	 * @return null
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// @todo check that phpBB is not already installed

		$this->iohandler_factory->set_environment('cli');

		/** @var \phpbb\install\helper\iohandler\cli_iohandler $iohandler */
		$iohandler = $this->iohandler_factory->get();
		$style = new SymfonyStyle($input, $output);
		$iohandler->set_style($style, $output);

		$processor = new Processor();
		$configuration = new installer_configuration();

		dump($configuration->getConfigTreeBuilder()->buildTree());

		try
		{
			$config = $processor->processConfiguration($configuration, array());
		}
		catch (Exception $e)
		{
			$iohandler->add_error_message('INVALID_CONFIGURATION', $e->getMessage());

			return;
		}

		dump($config);
	}

	/**
	 * Register the configuration to simulate the forms.
	 *
	 * @param cli_iohandler $iohandler
	 * @param array $config
	 */
	private function register_configuration(cli_iohandler $iohandler, $config)
	{
		$iohandler->set_input('admin_name', $config['admin']['name']);
		$iohandler->set_input('admin_pass1', $config['admin']['password']);
		$iohandler->set_input('admin_pass2', $config['admin']['password']);
		$iohandler->set_input('board_email', $config['admin']['email']);
		$iohandler->set_input('submit_admin', 'submit');

		$iohandler->set_input('default_lang', $config['board']['lang']);
		$iohandler->set_input('board_name', $config['board']['name']);
		$iohandler->set_input('board_description', $config['board']['description']);
		$iohandler->set_input('submit_board', 'submit');

		$iohandler->set_input('dbms', $config['database']['dbms']);
		$iohandler->set_input('dbhost', $config['database']['dbhost']);
		$iohandler->set_input('dbport', $config['database']['dbport']);
		$iohandler->set_input('dbuser', $config['database']['dbuser']);
		$iohandler->set_input('dbpasswd', $config['database']['dbpasswd']);
		$iohandler->set_input('dbname', $config['database']['dbname']);
		$iohandler->set_input('table_prefix', $config['database']['table_prefix']);
		$iohandler->set_input('submit_database', 'submit');

		$iohandler->set_input('email_enable', $config['email']['enabled']);
		$iohandler->set_input('smtp_delivery', $config['email']['smtp_delivery']);
		$iohandler->set_input('smtp_host', $config['email']['smtp_host']);
		$iohandler->set_input('smtp_auth', $config['email']['smtp_auth']);
		$iohandler->set_input('smtp_user', $config['email']['smtp_user']);
		$iohandler->set_input('smtp_pass', $config['email']['smtp_pass']);
		$iohandler->set_input('submit_email', 'submit');

		$iohandler->set_input('cookie_secure', $config['server']['cookie_secure']);
		$iohandler->set_input('server_protocol', $config['server']['server_protocol']);
		$iohandler->set_input('force_server_vars', $config['server']['force_server_vars']);
		$iohandler->set_input('server_name', $config['server']['server_name']);
		$iohandler->set_input('server_port', $config['server']['server_port']);
		$iohandler->set_input('script_path', $config['server']['script_path']);
		$iohandler->set_input('submit_server', 'submit');
	}
}
