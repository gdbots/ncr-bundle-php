<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
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

        $nodes = [];
        $f = function (Node $node, NodeRef $nodeRef) use (&$nodes) {
            $nodes[$nodeRef->toString()] = $node;
        };

        $ncr->streamNodes(SchemaQName::fromString('eme:user'), $f, ['account_id' => 1000]);

        $nodeRefs = [];
        foreach ($nodes as $nodeRef => $node) {
            $nodeRefs[] = NodeRef::fromString($nodeRef);
        }

        echo json_encode($nodeRefs, JSON_PRETTY_PRINT);

        $nodes = $ncr->getNodes($nodeRefs, false, ['account_id' => 1000]);

        foreach ($nodes as $nodeRef => $node) {
            echo $nodeRef . PHP_EOL;
            echo json_encode($node, JSON_PRETTY_PRINT);
        }
    }
}
