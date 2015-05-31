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

namespace phpbb\install\helper\iohandler;
use phpbb\install\exception\installer_exception;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Input-Output handler for the CLI frontend
 */
class cli_iohandler extends iohandler_base
{
	/**
	 * @var InputInterface
	 */
	protected $input;

	/**
	 * @var OutputInterface
	 */
	protected $output;

	/**
	 * @var StyleInterface
	 */
	protected $io;

	/**
	 * @var array
	 */
	protected $input_values = array();

	/**
	 * @param StyleInterface $style
	 */
	public function set_style(StyleInterface $style)
	{
		$this->io = $style;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_input($name, $default, $multibyte = false)
	{
		$result = $default;

		if (isset($this->input_values[$name]))
		{
			$result = $this->input_values[$name];
		}


		if ($multibyte)
		{
			return utf8_normalize_nfc($result);
		}

		return $result;
	}

	public function set_input($name, $value)
	{
		$this->input_values[$name] = $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_server_variable($name, $default = '')
	{
		return $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_header_variable($name, $default = '')
	{
		return $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_secure()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_user_form_group($title, $form)
	{
		throw new installer_exception('MISSING_DATA');
	}

	/**
	 * {@inheritdoc}
	 */
	public function send_response()
	{
	}

	public function add_error_message($error_title, $error_description = false)
	{
		$this->io->error($this->translate_message($error_title, $error_description));
	}

	public function add_warning_message($warning_title, $warning_description = false)
	{
		$this->io->warning($this->translate_message($warning_title, $warning_description));
	}

	public function add_log_message($log_title, $log_description = false)
	{
		$this->io->writeln($this->translate_message($log_title, $log_description));
	}


}
