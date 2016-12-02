<?php

namespace Gdbots\Bundle\NcrBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private $env;

    /**
     * @param string $env
     */
    public function __construct($env = 'dev')
    {
        $this->env = $env;
    }

    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gdbots_ncr');

        $rootNode
            ->children()
                ->arrayNode('ncr')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                        ->end()
                        ->append($this->getDynamoDbNcrConfigTree())
                    ->end()
                ->end()
                ->arrayNode('ncr_search')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                        ->end()
                        ->append($this->getElasticaNcrSearchConfigTree())
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @return NodeDefinition
     */
    protected function getDynamoDbNcrConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('dynamodb');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Ncr\Repository\DynamoDb\DynamoDbNcr')
                ->end()
                ->arrayNode('table_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Ncr\Repository\DynamoDb\TableManager')
                        ->end()
                        ->scalarNode('table_name_prefix')
                            ->defaultValue("{$this->env}-ncr")
                        ->end()
                        ->arrayNode('node_tables')
                            ->useAttributeAsKey('name')
                            ->treatNullLike([])
                            ->defaultValue([])
                            ->prototype('array')
                                ->fixXmlConfig('node_table')
                                ->performNoDeepMerging()
                                ->children()
                                    ->scalarNode('class')->end()
                                    ->scalarNode('table_name')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return NodeDefinition
     */
    protected function getElasticaNcrSearchConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('elastica');

        $defaultServers = [['host' => '127.0.0.1', 'port' => 9200]];
        $defaultCluster = [
            'default' => [
                'round_robin' => true,
                'timeout' => 300,
                'debug' => false,
                'persistent' => true,
                'servers' => $defaultServers
            ]
        ];

        $node
            ->fixXmlConfig('cluster')
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Ncr\Search\Elastica\ElasticaNcrSearch')
                ->end()
                ->arrayNode('index_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Ncr\Search\Elastica\IndexManager')
                        ->end()
                        ->scalarNode('index_prefix')->end()
                    ->end()
                ->end()
                ->scalarNode('query_timeout')
                    ->defaultValue('500ms')
                    ->treatNullLike('500ms')
                ->end()
                ->arrayNode('clusters')
                    ->useAttributeAsKey('name')
                    ->treatNullLike($defaultCluster)
                    ->defaultValue($defaultCluster)
                    ->prototype('array')
                        ->fixXmlConfig('server')
                        ->performNoDeepMerging()
                        ->children()
                            ->booleanNode('round_robin')
                                ->defaultTrue()
                                ->treatNullLike(true)
                            ->end()
                            ->integerNode('timeout')
                                ->info(
                                    'Number of seconds after a timeout occurs for every request. ' .
                                    'If using indexing of file large value necessary.'
                                )
                                ->defaultValue(300)
                                ->treatNullLike(300)
                            ->end()
                            ->booleanNode('debug')
                                ->defaultFalse()
                                ->treatNullLike(false)
                            ->end()
                            ->booleanNode('persistent')
                                ->defaultTrue()
                                ->treatNullLike(true)
                            ->end()
                            ->arrayNode('servers')
                                ->requiresAtLeastOneElement()
                                ->treatNullLike($defaultServers)
                                ->defaultValue($defaultServers)
                                ->prototype('array')
                                    ->performNoDeepMerging()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('host')
                                            ->defaultValue('127.0.0.1')
                                            ->treatNullLike('127.0.0.1')
                                        ->end()
                                        ->integerNode('port')
                                            ->defaultValue(9200)
                                            ->treatNullLike(9200)
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}