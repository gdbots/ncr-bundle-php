<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Controller;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrLazyLoader;
use Gdbots\Ncr\NcrSearch;

trait NcrAwareControllerTrait
{
    /**
     * @return Ncr
     */
    protected function getNcr(): Ncr
    {
        return $this->container->get('ncr');
    }

    /**
     * @return NcrCache
     */
    protected function getNcrCache(): NcrCache
    {
        return $this->container->get('ncr_cache');
    }

    /**
     * @return NcrLazyLoader
     */
    protected function getNcrLazyLoader(): NcrLazyLoader
    {
        return $this->container->get('ncr_lazy_loader');
    }

    /**
     * @return NcrSearch
     */
    protected function getNcrSearch(): NcrSearch
    {
        return $this->container->get('ncr_search');
    }
}
