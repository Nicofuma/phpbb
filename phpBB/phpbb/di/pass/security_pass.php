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

namespace phpbb\di\pass;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class security_pass implements CompilerPassInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function process(ContainerBuilder $container)
	{
		$this->make_public($container, ['twig.extension.logout_url', 'twig.extension.security']);
	}

	/**
	 * Make public a set of services
	 *
	 * @param array $ids
	 * @param ContainerBuilder $container
	 */
	private function make_public(ContainerBuilder $container, array $ids)
	{
		foreach ($ids as $id)
		{
			if ($container->hasDefinition($id))
			{
				$container->getDefinition($id)->setPublic(true);
			}
		}
	}
}
