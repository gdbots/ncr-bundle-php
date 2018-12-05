<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\DependencyInjection;

use Gdbots\Ncr\Repository\DynamoDb\DynamoDbNcr;
use Gdbots\Ncr\Repository\DynamoDb\TableManager;
use Gdbots\Ncr\Repository\Psr6Ncr;
use Gdbots\Ncr\Search\Elastica\ElasticaNcrSearch;
use Gdbots\Ncr\Search\Elastica\IndexManager;
use Gdbots\Ncr\Search\Elastica\NodeMapper;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private $env;

    /**
     * @param string $env
     */
    public function __construct(string $env = 'dev')
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
                        ->arrayNode('memoizing')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->treatNullLike(true)
                                ->end()
                                ->booleanNode('read_through')
                                    ->defaultFalse()
                                    ->treatNullLike(false)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('psr6')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->treatNullLike(true)
                                ->end()
                                ->scalarNode('provider')
                                    ->defaultValue('cache.app')
                                ->end()
                                ->booleanNode('read_through')
                                    ->defaultTrue()
                                    ->treatNullLike(true)
                                ->end()
                                ->scalarNode('class')
                                    ->defaultValue(Psr6Ncr::class)
                                ->end()
                            ->end()
                        ->end()
                        ->append($this->getDynamoDbNcrConfigTree())
                    ->end()
                ->end()
                ->arrayNode('ncr_cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_items')
                            ->defaultValue(500)
                        ->end()
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
                ->arrayNode('node_idempotency_validator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('ttl')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->treatNullLike([])
                            ->defaultValue([])
                            ->prototype('array')
                                ->fixXmlConfig('qname')
                                ->performNoDeepMerging()
                                ->integerPrototype()
                                    ->defaultValue(60)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @return NodeDefinition
     */
    protected function getDynamoDbNcrConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('dynamodb');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')
                    ->defaultValue(DynamoDbNcr::class)
                ->end()
                ->arrayNode('table_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue(TableManager::class)
                        ->end()
                        ->scalarNode('table_name_prefix')
                            ->defaultValue("{$this->env}-ncr")
                        ->end()
                        ->arrayNode('node_tables')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
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
                ->arrayNode('config')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')
                            ->defaultValue(100)
                        ->end()
                        ->integerNode('pool_size')
                            ->defaultValue(25)
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
    protected function getElasticaNcrSearchConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('elastica');

        $defaultServers = [['host' => '127.0.0.1', 'port' => 9200]];
        $defaultCluster = [
            'default' => [
                'round_robin' => true,
                'timeout'     => 300,
                'debug'       => false,
                'persistent'  => true,
                'servers'     => $defaultServers,
            ],
        ];

        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('cluster')
            ->children()
                ->scalarNode('class')
                    ->defaultValue(ElasticaNcrSearch::class)
                ->end()
                ->arrayNode('index_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue(IndexManager::class)
                        ->end()
                        ->scalarNode('index_prefix')
                            ->defaultValue("{$this->env}-ncr")
                        ->end()
                        ->arrayNode('indexes')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->treatNullLike([])
                            ->defaultValue([])
                            ->prototype('array')
                                ->fixXmlConfig('index')
                                ->performNoDeepMerging()
                                ->children()
                                    ->integerNode('number_of_shards')
                                        ->defaultValue(5)
                                    ->end()
                                    ->integerNode('number_of_replicas')
                                        ->defaultValue(1)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('types')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->treatNullLike([])
                            ->defaultValue([])
                            ->prototype('array')
                                ->fixXmlConfig('type')
                                ->performNoDeepMerging()
                                ->children()
                                    ->scalarNode('mapper_class')
                                        ->defaultValue(NodeMapper::class)
                                    ->end()
                                    ->scalarNode('index_name')->end()
                                    ->scalarNode('type_name')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('query_timeout')
                    ->defaultValue('500ms')
                    ->treatNullLike('500ms')
                ->end()
                ->arrayNode('clusters')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->treatNullLike($defaultCluster)
                    ->defaultValue($defaultCluster)
                    ->prototype('array')
                        ->fixXmlConfig('server')
                        ->addDefaultsIfNotSet()
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
