<?php

namespace Gdbots\Bundle\NcrBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class GdbotsNcrExtension extends Extension
{
    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration($container->getParameter('kernel.environment'));

        $config = $processor->processConfiguration($configuration, $config);
//echo json_encode($config, JSON_PRETTY_PRINT);
//exit;
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if (isset($config['ncr'])) {
            $container->setParameter('gdbots_ncr.ncr.provider', $config['ncr']['provider']);
            $this->configureDynamoDbNcr($config, $container, $config['ncr']['provider']);
        }

        if (isset($config['ncr_search'])) {
            $container->setParameter('gdbots_ncr.ncr_search.provider', $config['ncr_search']['provider']);
            $this->configureElasticaNcrSearch($config, $container, $config['ncr_search']['provider']);
        }
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @param string $provider
     */
    protected function configureDynamoDbNcr(array $config, ContainerBuilder $container, $provider)
    {
        $service = 'gdbots_ncr.ncr.dynamodb';

        if (!isset($config['ncr']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition($service);
            return;
        }

        $dynamodb = $config['ncr']['dynamodb'];
        $container->setParameter("{$service}.class", $dynamodb['class']);
        $container->setParameter("{$service}.table_manager.class", $dynamodb['table_manager']['class']);
        $container->setParameter("{$service}.table_manager.table_name_prefix", $dynamodb['table_manager']['table_name_prefix']);
        $container->setParameter("{$service}.table_manager.node_tables", $dynamodb['table_manager']['node_tables']);

        $container->setAlias('ncr', $service);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @param string $provider
     */
    protected function configureElasticaNcrSearch(array $config, ContainerBuilder $container, $provider)
    {
        if (!isset($config['ncr_search']['elastica']) || 'elastica' !== $provider) {
            $container->removeDefinition('gdbots_ncr.ncr_search.elastica');
            $container->removeDefinition('gdbots_ncr.ncr_search.elastica.client_manager');
            $container->removeDefinition('gdbots_ncr.ncr_search.elastica.index_manager');
            return;
        }

        $container->setParameter('gdbots_ncr.ncr_search.elastica.class', $config['ncr_search']['elastica']['class']);
        $container->setParameter('gdbots_ncr.ncr_search.elastica.index_manager.class', $config['ncr_search']['elastica']['index_manager']['class']);
        if (isset($config['ncr_search']['elastica']['index_manager']['index_prefix'])) {
            $container->setParameter(
                'gdbots_ncr.ncr_search.elastica.index_manager.index_prefix',
                $config['ncr_search']['elastica']['index_manager']['index_prefix']
            );
        }
        $container->setParameter('gdbots_ncr.ncr_search.elastica.query_timeout', $config['ncr_search']['elastica']['query_timeout']);
        $container->setParameter('gdbots_ncr.ncr_search.elastica.clusters', $config['ncr_search']['elastica']['clusters']);
    }
}
