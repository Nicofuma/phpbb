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

// Start session management
$user->setup();

/** @var \phpbb\routing\helper $routing_helper */
$routing_helper = $phpbb_container->get('routing.helper');

$response = new \Symfony\Component\HttpFoundation\RedirectResponse(
	$routing_helper->route(
		$request->variable('mode', 'faq') === 'bbcode' ? 'phpbb_help_bbcode_controller' : 'phpbb_help_faq_controller'
	),
	301
);
$response->send();
