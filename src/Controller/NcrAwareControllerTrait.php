<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Controller;

use Gdbots\Ncr\Ncr;
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
     * @return NcrSearch
     */
    protected function getNcrSearch(): NcrSearch
    {
        return $this->container->get('ncr_search');
    }
}
