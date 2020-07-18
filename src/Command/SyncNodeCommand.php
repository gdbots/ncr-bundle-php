<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\AggregateResolver;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SyncNodeCommand extends Command
{
    protected static $defaultName = 'ncr:sync-node';
    protected ContainerInterface $container;
    protected Ncr $ncr;
    protected NcrSearch $ncrSearch;
    protected Pbjx $pbjx;

    public function __construct(ContainerInterface $container, Ncr $ncr, NcrSearch $ncrSearch, Pbjx $pbjx)
    {
        $this->container = $container;
        $this->ncr = $ncr;
        $this->ncrSearch = $ncrSearch;
        $this->pbjx = $pbjx;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_ncr.ncr.provider');
        $searchProvider = $this->container->getParameter('gdbots_ncr.ncr_search.provider');
        $eventStoreProvider = $this->container->getParameter('gdbots_pbjx.event_store.provider');

        $this
            ->setDescription("Syncs a node from the Ncr ({$provider}) with the EventStore ({$eventStoreProvider}).")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will fetch a single node from the Ncr ({$provider})
for the given NodeRef provided and load the node's aggregate, perform a sync with the
EventStore ({$eventStoreProvider}) and then update the node in the Ncr ({$provider})
and NcrSearch ({$searchProvider}) with the synced value.

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'no-snapshot',
                null,
                InputOption::VALUE_NONE,
                'Rebuild the node from scratch.'
            )
            ->addOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                'Context to provide to the Ncr (json).'
            )
            ->addOption(
                'tenant-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Tenant Id to use for this operation.'
            )
            ->addArgument(
                'node-ref',
                InputArgument::REQUIRED,
                'The NodeRef of the node. e.g. "acme:article:123"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $noShapshot = $input->getOption('no-snapshot');
        $context = $input->getOption('context') ?: '{}';
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $nodeRef = NodeRef::fromString($input->getArgument('node-ref'));

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Syncing node "%s"', $nodeRef));
        $io->comment('context: ' . json_encode($context));
        $io->newLine();

        try {
            if ($noShapshot) {
                $aggregate = AggregateResolver::resolve($nodeRef->getQName())::fromNodeRef($nodeRef, $this->pbjx);
                $expectedEtag = null;
            } else {
                $node = $this->ncr->getNode($nodeRef, true, $context);
                $expectedEtag = $node->get('etag');
                $aggregate = AggregateResolver::resolve($nodeRef->getQName())::fromNode($node, $this->pbjx);
            }
        } catch (NodeNotFound $nf) {
            $aggregate = AggregateResolver::resolve($nodeRef->getQName())::fromNodeRef($nodeRef, $this->pbjx);
            $expectedEtag = null;
        } catch (\Throwable $e) {
            throw $e;
        }

        $aggregate->sync($context);
        $node = $aggregate->getNode();

        if ($aggregate->getEtag() !== $expectedEtag) {
            $this->ncr->putNode($node, $expectedEtag, $context);
            $this->ncrSearch->indexNodes([$node], $context);
        }

        $io->text(json_encode($node, JSON_PRETTY_PRINT));

        $io->newLine();
        $io->success(sprintf('Synced node "%s".', $nodeRef));

        return self::SUCCESS;
    }
}
