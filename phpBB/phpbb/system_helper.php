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

namespace phpbb;

use phpbb\config\config;

class system_helper
{
	/** @var float|bool */
	private $load;

	/** @var config */
	private $config;

	public function __construct(config $config)
	{
		$this->config = $config;
	}

	/**
	 * @return bool|float
	 */
	public function get_load()
	{
		if ($this->load === null)
		{
			$load = false;

			if ($this->config['limit_load'] || $this->config['limit_search_load'])
			{
				if ((function_exists('sys_getloadavg') && $load = sys_getloadavg())
					|| ($load = explode(' ', @file_get_contents('/proc/loadavg'))))
				{
					$load = array_slice($load, 0, 1);
					$load = (float) $load[0];
				}
				else
				{
					$this->config->set('limit_load', '0');
					$this->config->set('limit_search_load', '0');
				}
			}

			$this->load = $load;
		}
		return $this->load;
	}
}
