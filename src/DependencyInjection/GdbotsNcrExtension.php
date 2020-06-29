<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\DependencyInjection;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrLazyLoader;
use Gdbots\Ncr\NcrPreloader;
use Gdbots\Ncr\NcrSearch;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class GdbotsNcrExtension extends Extension
{
    public function load(array $config, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('ncr.xml');
        $loader->load('ncr_cache.xml');
        $loader->load('ncr_search.xml');
        $loader->load('services.xml');
        $loader->load('twig.xml');

        // ncr
        $container->setParameter('gdbots_ncr.ncr.provider', $config['ncr']['provider']);
        if ($config['ncr']['memoizing']['enabled']) {
            $container->setParameter('gdbots_ncr.ncr.memoizing.read_through', $config['ncr']['memoizing']['read_through']);
        } else {
            $container->removeDefinition('gdbots_ncr.ncr.memoizing');
        }
        $this->configurePsr6Ncr($config, $container);
        $this->configureDynamoDbNcr($config, $container, $config['ncr']['provider']);

        // ncr_cache
        $container->setParameter('gdbots_ncr.ncr_cache.max_items', $config['ncr_cache']['max_items']);
        $container->setAlias(NcrCache::class, 'ncr_cache');
        $container->setAlias(NcrLazyLoader::class, 'ncr_lazy_loader');
        $container->setAlias(NcrPreloader::class, 'ncr_preloader');

        // ncr_search
        $container->setParameter('gdbots_ncr.ncr_search.provider', $config['ncr_search']['provider']);
        $this->configureElasticaNcrSearch($config, $container, $config['ncr_search']['provider']);

        // node_idempotency_validator
        $container->setParameter('gdbots_ncr.node_idempotency_validator.ttl', $config['node_idempotency_validator']['ttl']);
    }

    protected function configurePsr6Ncr(array $config, ContainerBuilder $container): void
    {
        $service = 'gdbots_ncr.ncr.psr6';
        $psr6 = $config['ncr']['psr6'];

        if (!$psr6['enabled']) {
            $container->removeDefinition($service);
            return;
        }

        $container->setParameter("{$service}.class", $psr6['class']);
        $container->setParameter("{$service}.read_through", $psr6['read_through']);

        if (isset($psr6['provider'])) {
            $container->getDefinition($service)->replaceArgument(1, new Reference($psr6['provider']));
        }
    }

    protected function configureDynamoDbNcr(array $config, ContainerBuilder $container, ?string $provider): void
    {
        $service = 'gdbots_ncr.ncr.dynamodb';

        if (!isset($config['ncr']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition($service);
            $container->removeDefinition("{$service}.table_manager");
            return;
        }

        $dynamodb = $config['ncr']['dynamodb'];
        $container->setParameter("{$service}.class", $dynamodb['class']);
        $container->setParameter("{$service}.table_manager.class", $dynamodb['table_manager']['class']);
        $container->setParameter("{$service}.table_manager.table_name_prefix", $dynamodb['table_manager']['table_name_prefix']);
        $container->setParameter("{$service}.table_manager.node_tables", $dynamodb['table_manager']['node_tables']);
        $container->setParameter("{$service}.config", [
            'batch_size'  => $dynamodb['config']['batch_size'],
            'concurrency' => $dynamodb['config']['concurrency'],
        ]);

        $container->setAlias('ncr', $service);
        $container->setAlias(Ncr::class, 'ncr');
    }

    protected function configureElasticaNcrSearch(array $config, ContainerBuilder $container, ?string $provider): void
    {
        $service = 'gdbots_ncr.ncr_search.elastica';

        if (!isset($config['ncr_search']['elastica']) || 'elastica' !== $provider) {
            $container->removeDefinition($service);
            $container->removeDefinition("{$service}.client_manager");
            $container->removeDefinition("{$service}.index_manager");
            return;
        }

        $elastica = $config['ncr_search']['elastica'];
        $container->setParameter("{$service}.class", $elastica['class']);
        $container->setParameter("{$service}.index_manager.class", $elastica['index_manager']['class']);
        $container->setParameter("{$service}.index_manager.index_prefix", $elastica['index_manager']['index_prefix']);
        $container->setParameter("{$service}.index_manager.indexes", $elastica['index_manager']['indexes']);
        $container->setParameter("{$service}.index_manager.types", $elastica['index_manager']['types']);
        $container->setParameter("{$service}.query_timeout", $elastica['query_timeout']);
        $container->setParameter("{$service}.clusters", $elastica['clusters']);

        $container->setAlias('ncr_search', $service);
        $container->setAlias(NcrSearch::class, 'ncr_search');
    }
}
