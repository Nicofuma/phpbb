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

namespace phpbb\install;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class installer_configuration implements ConfigurationInterface
{

	/**
	 * Generates the configuration tree builder.
	 *
	 * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('installer');
		$rootNode
			->children()
				->arrayNode('admin')
					->children()
						->scalarNode('name')->defaultValue('admin')->end()
						->scalarNode('password')->defaultValue('adminadmin')->end()
						->scalarNode('email')->defaultValue('admin@example.org')->end()
					->end()
				->end()
				->arrayNode('board')
					->children()
						->scalarNode('lang')->defaultValue('en')->end()
						->scalarNode('name')->defaultValue('My Board')->end()
						->scalarNode('description')->defaultValue('My amazing new phpBB board')->end()
					->end()
				->end()
				->arrayNode('database')
					->children()
						->scalarNode('dbms')->defaultValue('sqlite3')->end()
						->scalarNode('dbhost')->defaultValue(null)->end()
						->scalarNode('dbport')->defaultValue(null)->end()
						->scalarNode('dbuser')->defaultValue(null)->end()
						->scalarNode('dbpasswd')->defaultValue(null)->end()
						->scalarNode('dbname')->defaultValue(null)->end()
						->scalarNode('table_prefix')->defaultValue('phpbb_')->end()
					->end()
				->end()
				->arrayNode('email')
					->addDefaultsIfNotSet()
					->canBeEnabled()
					->children()
						->booleanNode('smtp_delivery')->defaultValue(false)->end()
						->scalarNode('smtp_host')->defaultValue(null)->end()
						->scalarNode('smtp_auth')->defaultValue(null)->end()
						->scalarNode('smtp_user')->defaultValue(null)->end()
						->scalarNode('smtp_pass')->defaultValue(null)->end()
					->end()
				->end()
				->arrayNode('server')
					->children()
						->booleanNode('cookie_secure')->defaultValue(false)->end()
						->scalarNode('server_protocol')->defaultValue('http://')->end()
						->booleanNode('force_server_vars')->defaultValue(false)->end()
						->scalarNode('server_name')->defaultValue('localhost')->end()
						->integerNode('server_port')->defaultValue(80)->end()
						->scalarNode('script_path')->defaultValue('/')->end()
					->end()
				->end()
			->end()
		;
		return $treeBuilder;
	}
}
