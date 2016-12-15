<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Schemas\Ncr\NodeRef;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NcrCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:test');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);
        $io->title('NCR Test');

        /** @var Ncr $ncr */
        $ncr = $container->get('ncr');


        $nodeRefs = [
            NodeRef::fromString('eme:account:1000'),
            NodeRef::fromString('eme:account:2000'),
            NodeRef::fromString('eme:account:3000'),
            NodeRef::fromString('eme:account:4000'),
            NodeRef::fromString('eme:user:abc'),
        ];

        $nodes = $ncr->getNodes($nodeRefs, true, ['account_id' => 1000]);
    }
}
