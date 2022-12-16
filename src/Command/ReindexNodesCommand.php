<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbj\Util\NumberUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'ncr:reindex-nodes')]
final class ReindexNodesCommand extends Command
{
    public function __construct(
        protected ContainerInterface $container,
        protected Ncr $ncr,
        protected NcrSearch $ncrSearch
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_ncr.ncr.provider');
        $searchProvider = $this->container->getParameter('gdbots_ncr.ncr_search.provider');

        $this
            ->setDescription('Pipes nodes from the Ncr and reindexes them.')
            ->setDescription("Pipes node from the Ncr ({$provider}) and reindexes them")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe nodes from the Ncr ({$provider})
for the given SchemaQName if provided or all nodes and reindex them into the NcrSearch ({$searchProvider}) service.

<info>php %command.full_name% --dry-run --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pipes nodes and renders output but will NOT actually reindex.'
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any batches that fail to reindex.'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of nodes to reindex at a time.',
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
        $dryRun = $input->getOption('dry-run');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtil::bound((int)$input->getOption('batch-size'), 1, 2000);
        $batchDelay = NumberUtil::bound((int)$input->getOption('batch-delay'), 10, 600000);
        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['skip_errors'] = $skipErrors;
        $context['reindexing'] = true;
        $qname = $input->getArgument('qname') ? SchemaQName::fromString($input->getArgument('qname')) : null;
        $context['reindex_all'] = null === $qname;

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Reindexing nodes for qname "%s"', $qname ?? 'ALL'));
        $io->comment('context: ' . json_encode($context));
        $io->newLine();

        $batch = 1;
        $i = 0;
        $reindexed = 0;
        $queue = [];

        $qnames = $qname
            ? [$qname]
            : array_map(
                fn(string $curie) => SchemaCurie::fromString($curie)->getQName(),
                MessageResolver::findAllUsingMixin('gdbots:ncr:mixin:node:v1', false)
            );

        foreach ($qnames as $q) {
            /** @var Message $node */
            foreach ($this->ncr->pipeNodes($q, $context) as $node) {
                ++$i;
                $queue[] = $node->freeze();

                $io->text(sprintf(
                    '<info>%d.</info> <comment>node_ref:</comment>%s, <comment>status:</comment>%s, ' .
                    '<comment>etag:</comment>%s, <comment>title:</comment>%s',
                    $i,
                    $node->generateNodeRef(),
                    $node->fget('status'),
                    $node->get('etag'),
                    $node->get('title')
                ));

                if (count($queue) >= $batchSize) {
                    $nodes = $queue;
                    $queue = [];
                    $reindexed += $this->reindexBatch($io, $nodes, $context, $batch, $dryRun, $skipErrors);
                    ++$batch;
                    usleep($batchDelay * 1000);
                }
            }
        }

        $reindexed += $this->reindexBatch($io, $queue, $context, $batch, $dryRun, $skipErrors);
        $io->newLine();
        $io->success(sprintf(
            'Reindexed %s of %s nodes for qname "%s".',
            number_format($reindexed),
            number_format($i),
            $qname ?? 'ALL'
        ));

        return self::SUCCESS;
    }

    protected function reindexBatch(SymfonyStyle $io, array $nodes, array $context, int $batch, bool $dryRun, bool $skipErrors): int
    {
        if (empty($nodes)) {
            return 0;
        }

        if ($dryRun) {
            $io->note(sprintf('DRY RUN - Would reindex node batch %d here.', $batch));
            return count($nodes);
        }

        try {
            return $this->reindex($nodes, $context, $skipErrors);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->note(sprintf('Failed to index batch %d.', $batch));
            $io->newLine(2);

            if (!$skipErrors) {
                throw $e;
            }
        }

        return 0;
    }

    protected function reindex(array $nodes, array $context, bool $skipErrors): int
    {
        $count = count($nodes);
        if ($count === 0) {
            return 0;
        }

        try {
            $this->ncrSearch->indexNodes($nodes, $context);
            return $count;
        } catch (\Throwable $e) {
            // in case of failure try again with smaller batch sizes and delay
            $chunks = array_chunk($nodes, (int)(ceil($count / 10)));
            $indexed = 0;

            foreach ($chunks as $chunk) {
                try {
                    usleep(100000);
                    $this->ncrSearch->indexNodes($chunk, $context);
                    $indexed += count($chunk);
                } catch (\Throwable $e2) {
                    if (!$skipErrors) {
                        throw $e2;
                    }
                }
            }

            return $indexed;
        }
    }
}
