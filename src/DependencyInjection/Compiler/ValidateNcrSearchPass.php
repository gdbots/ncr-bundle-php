<?php

namespace Gdbots\Bundle\NcrBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Checks the container to ensure that the NCR search has the provider defined
 * and that it's valid.
 */
class ValidateNcrSearchPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('gdbots_ncr.ncr_search.provider')) {
            return;
        }

        $provider = $container->getParameter('gdbots_ncr.ncr_search.provider');
        if (empty($provider)) {
            return;
        }

        $this->ensureProviderExists($container, $provider);

        switch ($provider) {
            case 'elastica':
                $this->validateElasticaProvider($container);
                break;
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $provider
     *
     * @throws \LogicException
     */
    private function ensureProviderExists(ContainerBuilder $container, $provider)
    {
        $serviceId = 'gdbots_ncr.ncr_search.'.$provider;
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'The "gdbots_ncr.ncr_search.provider" is configured to use "%s" which requires service "%s".',
                $provider,
                $serviceId
            )
        );
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     */
    private function validateElasticaProvider(ContainerBuilder $container)
    {
        // validate here
    }
}
