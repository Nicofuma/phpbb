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

namespace phpbb\security;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall as FirewallBase;

class firewall extends FirewallBase
{
	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::REQUEST => ['onKernelRequest', 40],
			KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
		];
	}
}
