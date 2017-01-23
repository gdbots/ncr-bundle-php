<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Bundle\PbjxBundle\Command\PbjxAwareCommandTrait;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrLazyLoader;
use Gdbots\Ncr\NcrSearch;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method ContainerInterface getContainer()
 */
trait NcrAwareCommandTrait
{
    use PbjxAwareCommandTrait;

    /**
     * @param SymfonyStyle $io
     * @param string       $message
     *
     * @return bool
     */
    protected function readyForNcrTraffic(SymfonyStyle $io, $message = 'Aborting read of nodes.'): bool
    {
        $container = $this->getContainer();
        $question = sprintf(
            'Have you prepared your Ncr [%s] and your devops team for the added traffic? ',
            $container->getParameter('gdbots_ncr.ncr.provider')
        );

        if (!$io->confirm($question)) {
            $io->note($message);
            return false;
        }

        return true;
    }

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
