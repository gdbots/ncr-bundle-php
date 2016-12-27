<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrLazyLoader;
use Gdbots\Ncr\NcrSearch;
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
}
