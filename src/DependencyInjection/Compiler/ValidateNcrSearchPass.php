<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the container to ensure that the NCR search has the provider defined
 * and that it's valid.
 */
final class ValidateNcrSearchPass implements CompilerPassInterface
{
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

    private function ensureProviderExists(ContainerBuilder $container, string $provider): void
    {
        $serviceId = "gdbots_ncr.ncr_search.{$provider}";
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

    private function validateElasticaProvider(ContainerBuilder $container): void
    {
        // validate here
    }
}
