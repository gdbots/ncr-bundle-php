<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\AggregateResolver;
use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SyncNodesCommand extends Command
{
    protected static $defaultName = 'ncr:sync-nodes';
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
            ->setDescription("Syncs nodes from the Ncr ({$provider}) with the EventStore ({$eventStoreProvider}).")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe nodes from the Ncr ({$provider})
for the given SchemaQName if provided or all nodes and load the node's aggregate,
perform a sync with the EventStore ({$eventStoreProvider}) and then update the node
in the Ncr ({$provider}) and NcrSearch ({$searchProvider}) with the synced value.

<error> WARNING </error> This can take a LONG time to run.

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of nodes to sync at a time.',
                100
            )
            ->addOption(
                'batch-delay',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of milliseconds (1000 = 1 second) to delay between batches.',
                1000
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
                'qname',
                InputArgument::OPTIONAL,
                'The SchemaQName of the node. e.g. "acme:article"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = NumberUtil::bound((int)$input->getOption('batch-size'), 1, 2000);
        $batchDelay = NumberUtil::bound((int)$input->getOption('batch-delay'), 10, 600000);
        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['syncing'] = true;
        $qname = $input->getArgument('qname') ? SchemaQName::fromString($input->getArgument('qname')) : null;
        $context['syncing_all'] = null === $qname;

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Syncing nodes for qname "%s"', $qname ?? 'ALL'));
        $io->comment('context: ' . json_encode($context));
        $io->newLine();

        $i = 0;
        $synced = 0;

        $qnames = $qname
            ? [$qname]
            : array_map(
                fn(string $curie) => SchemaCurie::fromString($curie)->getQName(),
                MessageResolver::findAllUsingMixin('gdbots:ncr:mixin:node:v1', false)
            );

        foreach ($qnames as $qname) {
            /** @var Message $node */
            foreach ($this->ncr->pipeNodes($qname, $context) as $node) {
                ++$i;
                $nodeRef = $node->generateNodeRef();

                try {
                    $expectedEtag = $node->get('etag');
                    $aggregate = AggregateResolver::resolve($nodeRef->getQName())::fromNode($node, $this->pbjx);
                    $aggregate->sync($context);

                    if ($aggregate->getEtag() !== $expectedEtag) {
                        $node = $aggregate->getNode();
                        $this->ncr->putNode($node, $expectedEtag, $context);
                        $this->ncrSearch->indexNodes([$node], $context);
                    }

                    $io->text(sprintf(
                        '<info>%d.</info> %s <comment>node_ref:</comment>%s, <comment>status:</comment>%s, ' .
                        '<comment>etag:</comment>%s, <comment>title:</comment>%s',
                        $i,
                        $aggregate->getEtag() !== $expectedEtag ? 'SYNCED' : 'MATCHED',
                        $nodeRef,
                        $node->fget('status'),
                        $node->get('etag'),
                        $node->get('title')
                    ));

                    ++$synced;
                } catch (\Throwable $e) {
                    $io->text(sprintf('<info>%d.</info> FAILED <comment>node_ref:</comment>%s', $i, $nodeRef));
                    $io->error($e->getMessage());
                    $io->newLine(2);
                }

                if (0 === $i % $batchSize) {
                    usleep($batchDelay * 1000);
                }
            }
        }

        $io->newLine();
        $io->success(sprintf(
            'Synced %s of %s nodes for qname "%s".',
            number_format($synced),
            number_format($i),
            $qname ?? 'ALL'
        ));

        return self::SUCCESS;
    }
}
