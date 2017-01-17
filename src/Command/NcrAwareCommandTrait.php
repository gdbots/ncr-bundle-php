<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrLazyLoader;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaQName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method ContainerInterface getContainer()
 */
trait NcrAwareCommandTrait
{
    /**
     * @return Ncr
     */
    protected function getNcr(): Ncr
    {
        return $this->getContainer()->get('ncr');
    }

    /**
     * @return NcrCache
     */
    protected function getNcrCache(): NcrCache
    {
        return $this->getContainer()->get('ncr_cache');
    }

    /**
     * @return NcrLazyLoader
     */
    protected function getNcrLazyLoader(): NcrLazyLoader
    {
        return $this->getContainer()->get('ncr_lazy_loader');
    }

    /**
     * @return NcrSearch
     */
    protected function getNcrSearch(): NcrSearch
    {
        return $this->getContainer()->get('ncr_search');
    }

    /**
     * @param Mixin  $mixin
     * @param string $qname
     *
     * @return Schema[]
     */
    protected function getSchemas(Mixin $mixin, ?string $qname = null): array
    {
        $curie = $mixin->getId()->getCurieMajor();

        if (null === $qname) {
            $schemas = MessageResolver::findAllUsingMixin($mixin);
        } else {
            /** @var Message $class */
            $class = MessageResolver::resolveCurie(
                MessageResolver::resolveQName(SchemaQName::fromString($qname))
            );
            $schemas = [$class::schema()];
        }

        foreach ($schemas as $schema) {
            if (!$schema->hasMixin($curie) || !$schema->hasMixin($curie)) {
                throw new \InvalidArgumentException(
                    sprintf('The SchemaQName [%s] does not have mixin [%s].', $qname, $curie)
                );
            }
        }

        return $schemas;
    }
}
