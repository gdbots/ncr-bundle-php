<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ExportNodesCommand extends Command
{
    protected static $defaultName = 'ncr:export-nodes';
    protected ContainerInterface $container;
    protected Ncr $ncr;

    public function __construct(ContainerInterface $container, Ncr $ncr)
    {
        $this->container = $container;
        $this->ncr = $ncr;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_ncr.ncr.provider');

        $this
            ->setDescription("Pipes nodes from the Ncr ({$provider}) to STDOUT")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe nodes from the Ncr ({$provider})
for the given SchemaQName if provided or all nodes and write the json value of the
node on one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of nodes to export at a time.',
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
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $batchSize = NumberUtil::bound((int)$input->getOption('batch-size'), 1, 2000);
        $batchDelay = NumberUtil::bound((int)$input->getOption('batch-delay'), 100, 600000);
        $context = $input->getOption('context') ?: '{}';
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['exporting'] = true;
        $qname = $input->getArgument('qname') ? SchemaQName::fromString($input->getArgument('qname')) : null;
        $context['exporting_all'] = null === $qname;

        $qnames = $qname
            ? [$qname]
            : array_map(
                fn(string $curie) => SchemaCurie::fromString($curie)->getQName(),
                MessageResolver::findAllUsingMixin(NodeV1Mixin::SCHEMA_CURIE_MAJOR, false)
            );

        $i = 0;
        foreach ($qnames as $qname) {
            foreach ($this->ncr->pipeNodes($qname, $context) as $node) {
                ++$i;

                try {
                    echo json_encode($node) . PHP_EOL;
                } catch (\Throwable $e) {
                    $errOutput->writeln($e->getMessage());
                }

                if (0 === $i % $batchSize) {
                    usleep($batchDelay * 1000);
                }
            }
        }

        return self::SUCCESS;
    }
}
