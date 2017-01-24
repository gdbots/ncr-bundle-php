<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
write the json value of the node on one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
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
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $context = json_decode($input->getOption('context') ?: '{}', true);
        $context['tenant_id'] = $input->getOption('tenant-id');

        $ncr = $this->getNcr();
        $i = 0;

        $output->writeln('coming soon');

        /*
        $receiver = function (Node $node) use ($errOutput, $batchSize, $batchDelay, &$i) {
            ++$i;

            try {
                echo json_encode($node) . PHP_EOL;
            } catch (\Exception $e) {
                $errOutput->writeln($e->getMessage());
            }

            if (0 === $i % $batchSize) {
                if ($batchDelay > 0) {
                    usleep($batchDelay * 1000);
                }
            }
        };

        foreach ($this->getSchemasUsingMixin(NodeV1Mixin::create(), $input->getArgument('qname')) as $schema) {
            $ncr->pipeNodes($schema->getQName(), $receiver, $context);
        }
        */
    }
}