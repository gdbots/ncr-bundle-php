<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Bundle\PbjxBundle\Command\PbjxAwareCommandTrait;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Ncr\Request\GetNodeBatchRequestV1;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NcrCommand extends ContainerAwareCommand
{
    use NcrAwareCommandTrait;
    use PbjxAwareCommandTrait;

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
        $io = new SymfonyStyle($input, $output);
        $io->title('NCR Test');

        $ncr = $this->getNcr();
        $pbjx = $this->getPbjx();

        $nodeRefs = [];
        $f = function (NodeRef $nodeRef) use (&$nodeRefs) {
            $nodeRefs[] = $nodeRef;
        };

        $this->createConsoleRequest();

        $ncr->streamNodeRefs(SchemaQName::fromString('eme:user'), $f, ['account_id' => '1000']);

        shuffle($nodeRefs);
        $nodeRefs = array_slice($nodeRefs, 0, 5);

        $request = GetNodeBatchRequestV1::create()
            ->addToSet('node_refs', $nodeRefs)
            ->addToMap('hints', 'account_id', '1000');
        $nodes = $pbjx->request($request)->get('nodes');//$ncr->getNodes($nodeRefs, false, ['account_id' => '1000']);
        usort($nodes, function (Node $a, Node $b) {
            return strcmp($a->get('email'), $b->get('email'));
        });
        foreach ($nodes as $nodeRef => $node) {
            //echo $nodeRef . PHP_EOL;
            echo json_encode($node->get('_id') . ' => ' . $node->get('email')).PHP_EOL;
        }

        echo str_repeat('=', 50).PHP_EOL;

        //$nodes = $ncr->getNodes($nodeRefs, false, ['account_id' => '1000']);
        $request = clone $request;
        $nodes = $pbjx->request($request)->get('nodes');//$ncr->getNodes($nodeRefs, false, ['account_id' => '1000']);
        usort($nodes, function (Node $a, Node $b) {
            return strcmp($a->get('email'), $b->get('email'));
        });
        foreach ($nodes as $nodeRef => $node) {
            //echo $nodeRef . PHP_EOL;
            echo json_encode($node->get('_id') . ' => ' . $node->get('email')).PHP_EOL;
        }
    }
}
