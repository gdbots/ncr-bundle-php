<?php

namespace Gdbots\Bundle\NcrBundle\Controller;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;

trait NcrAwareControllerTrait
{
    /**
     * @return Ncr
     */
    protected function getNcr()
    {
        return $this->container->get('ncr');
    }

    /**
     * @return NcrSearch
     */
    protected function getNcrSearch()
    {
        return $this->container->get('ncr_search');
    }
}
