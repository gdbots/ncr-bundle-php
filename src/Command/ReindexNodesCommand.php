<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Schemas\Ncr\Mixin\Indexed\Indexed;
use Gdbots\Schemas\Ncr\Mixin\Indexed\IndexedV1Mixin;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReindexNodesCommand extends ContainerAwareCommand
{
    use NcrAwareCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:reindex-nodes')
            ->setDescription('Pipes nodes from the Ncr and reindexes them.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe nodes from the Ncr for the given 
SchemaQName if provided or all schemas having the mixin "gdbots:ncr:mixin:indexed" and 
and reindex them into the NcrSearch service.

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

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption('dry-run');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $context = json_decode($input->getOption('context') ?: '{}', true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['skip_errors'] = $skipErrors;
        $context['reindexing'] = true;
        $qname = $input->getArgument('qname') ? SchemaQName::fromString($input->getArgument('qname')) : null;

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Reindexing nodes for qname "%s"', $qname ?? 'ALL'));
        if (!$this->readyForNcrTraffic($io)) {
            return;
        }

        $ncr = $this->getNcr();
        $batch = 1;
        $i = 0;
        $reindexed = 0;
        $queue = [];
        //$io->comment(sprintf('Processing batch %d for qname "%s".', $batch, $qname ?? 'ALL'));
        $io->comment(sprintf('context: %s', json_encode($context)));
        $io->newLine();

        $receiver = function (Node $node) use (
            $output,
            $io,
            $context,
            $dryRun,
            $skipErrors,
            $batchSize,
            $batchDelay,
            &$batch,
            &$reindexed,
            &$i,
            &$queue
        ) {
            if (!$node instanceof Indexed) {
                $io->note(sprintf(
                    'IGNORING - Node [%s] does not have mixin [gdbots:ncr:mixin:indexed].',
                    NodeRef::fromNode($node)
                ));
                return;
            }

            ++$i;
            $output->writeln(
                sprintf(
                    '<info>%d.</info> <comment>node_ref:</comment>%s, <comment>status:</comment>%s, ' .
                    '<comment>etag:</comment>%s, <comment>title:</comment>%s',
                    $i,
                    NodeRef::fromNode($node),
                    $node->get('status'),
                    $node->get('etag'),
                    $node->get('title')
                )
            );
            $queue[] = $node->freeze();

            if (count($queue) >= $batchSize) {
                $nodes = $queue;
                $queue = [];
                $this->reindex($nodes, $reindexed, $io, $context, $batch, $dryRun, $skipErrors);
                ++$batch;

                if ($batchDelay > 0) {
                    //$io->newLine();
                    //$io->note(sprintf('Pausing for %d milliseconds.', $batchDelay));
                    usleep($batchDelay * 1000);
                }

                //$io->comment(sprintf('Processing batch %d.', $batch));
                //$io->newLine();
            }
        };

        foreach ($this->getSchemasUsingMixin(IndexedV1Mixin::create(), (string)$qname ?: null) as $schema) {
            $ncr->pipeNodes($schema->getQName(), $receiver, $context);
        }

        $this->reindex($queue, $reindexed, $io, $context, $batch, $dryRun, $skipErrors);
        $io->newLine();
        $io->success(sprintf('Reindexed %s nodes for qname "%s".', number_format($reindexed), $qname ?? 'ALL'));
    }

    /**
     * @param array        $nodes
     * @param int          $reindexed
     * @param SymfonyStyle $io
     * @param array        $context
     * @param int          $batch
     * @param bool         $dryRun
     * @param bool         $skipErrors
     *
     * @throws \Exception
     */
    protected function reindex(array $nodes, int &$reindexed, SymfonyStyle $io, array $context, int $batch, bool $dryRun = false, bool $skipErrors = false): void
    {
        if ($dryRun) {
            $io->note(sprintf('DRY RUN - Would reindex node batch %d here.', $batch));
        } else {
            try {
                $this->getNcrSearch()->indexNodes($nodes, $context);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                $io->note(sprintf('Failed to index batch %d.', $batch));
                $io->newLine(2);

                if (!$skipErrors) {
                    throw $e;
                }
            }
        }

        $reindexed += count($nodes);
    }
}
