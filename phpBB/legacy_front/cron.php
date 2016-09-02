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

use Symfony\Component\HttpFoundation\RedirectResponse;


$cron_type = $request->variable('cron_type', '');

$get_params_array = $request->get_super_global(\phpbb\request\request_interface::GET);

/** @var \phpbb\routing\helper $routing_helper */
$routing_helper = $phpbb_container->get('routing.helper');
$response = new RedirectResponse(
	$routing_helper->route('phpbb_cron_run', $get_params_array),
	301
);
$response->send();
