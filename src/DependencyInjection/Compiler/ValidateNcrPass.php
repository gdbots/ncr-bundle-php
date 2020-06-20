<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the container to ensure that the NCR has the provider defined and that it's valid.
 */
final class ValidateNcrPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('gdbots_ncr.ncr.provider')) {
            return;
        }

        $provider = $container->getParameter('gdbots_ncr.ncr.provider');
        if (empty($provider)) {
            return;
        }

        $this->ensureProviderExists($container, $provider);

        switch ($provider) {
            case 'dynamodb':
                $this->validateDynamoDbProvider($container);
                break;
        }
    }

    private function ensureProviderExists(ContainerBuilder $container, string $provider): void
    {
        $serviceId = "gdbots_ncr.ncr.{$provider}";
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'The "gdbots_ncr.ncr.provider" is configured to use "%s" which requires service "%s".',
                $provider,
                $serviceId
            )
        );
    }

    private function validateDynamoDbProvider(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('aws.dynamodb')) {
            throw new \LogicException(
                'The service "gdbots_ncr.ncr.dynamodb" has a dependency on a non-existent ' .
                'service "aws.dynamodb". This expects the DynamoDb Client that comes from ' .
                'composer package "aws/aws-sdk-php-symfony": "^1.0".'
            );
        }
    }
}
