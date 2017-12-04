<?php
declare(strict_types=1);

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
trait NcrCommandTrait
{
    use PbjxAwareCommandTrait;

    /** @var Ncr */
    protected $ncr;

    /** @var NcrCache */
    protected $ncrCache;

    /** @var NcrLazyLoader */
    protected $ncrLazyLoader;

    /** @var NcrSearch */
    protected $ncrSearch;

    /**
     * @param SymfonyStyle $io
     * @param string       $message
     *
     * @return bool
     */
    protected function readyForNcrTraffic(SymfonyStyle $io, string $message = 'Aborting read of nodes.'): bool
    {
        $question = sprintf(
            'Have you prepared your Ncr [%s] and your devops team for the added traffic? ',
            $this->getContainer()->getParameter('gdbots_ncr.ncr.provider')
        );

        if (!$io->confirm($question)) {
            $io->note($message);
            return false;
        }

        return true;
    }
}
