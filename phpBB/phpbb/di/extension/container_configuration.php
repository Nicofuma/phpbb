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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class container_configuration implements ConfigurationInterface
{

	/**
	 * Generates the configuration tree builder.
	 *
	 * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('core');
		$rootNode
			->children()
				->booleanNode('require_dev_dependencies')->defaultValue(false)->end()
				->arrayNode('debug')
					->addDefaultsIfNotSet()
					->children()
						->booleanNode('exceptions')->defaultValue(false)->end()
					->end()
				->end()
				->arrayNode('twig')
					->addDefaultsIfNotSet()
					->children()
						->booleanNode('debug')->defaultValue(null)->end()
						->booleanNode('auto_reload')->defaultValue(null)->end()
						->booleanNode('enable_debug_extension')->defaultValue(false)->end()
					->end()
				->end()
			->end()
		;

		$this->addSessionSection($rootNode);

		return $treeBuilder;
	}

	private function addSessionSection(ArrayNodeDefinition $rootNode)
	{
		$rootNode
			->children()
				->arrayNode('session')
					->info('session configuration')
					->children()
						->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
						->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
						->scalarNode('name')->end()
						->scalarNode('cookie_lifetime')->end()
						->scalarNode('cookie_path')->end()
						->scalarNode('cookie_domain')->end()
						->booleanNode('cookie_secure')->end()
						->booleanNode('cookie_httponly')->defaultTrue()->end()
						->booleanNode('use_cookies')->end()
						->scalarNode('gc_divisor')->end()
						->scalarNode('gc_probability')->defaultValue(1)->end()
						->scalarNode('gc_maxlifetime')->end()
						->scalarNode('save_path')->defaultValue('%core.cache_dir%/sessions')->end()
						->integerNode('metadata_update_threshold')
							->defaultValue('0')
							->info('seconds to wait between 2 session metadata updates, it will also prevent the session handler to write if the session has not changed')
						->end()
					->end()
				->end()
			->end()
		;
	}
}
