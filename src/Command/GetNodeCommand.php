<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\WellKnown\NodeRef;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class GetNodeCommand extends Command
{
    protected static $defaultName = 'ncr:get-node';
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
            ->setDescription('Fetches a single node by its NodeRef and writes to STDOUT.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will fetch a single node from the Ncr ({$provider})
for the given NodeRef provided and write the json value of the node to STDOUT.

<info>php %command.full_name% --tenant-id=client1 --consistent 'acme:article:123'</info>

EOF
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
            ->addOption(
                'consistent',
                null,
                InputOption::VALUE_NONE,
                'Fetches the node with a consistent read request.'
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Prints the json response with JSON_PRETTY_PRINT.'
            )
            ->addArgument(
                'node-ref',
                InputArgument::REQUIRED,
                'The NodeRef of the node. e.g. "acme:article:123"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $nodeRef = NodeRef::fromString($input->getArgument('node-ref'));
        $consistent = (bool)$input->getOption('consistent');

        try {
            $node = $this->ncr->getNode($nodeRef, $consistent, $context);
            echo json_encode($node, $input->getOption('pretty') ? JSON_PRETTY_PRINT : 0) . PHP_EOL;
        } catch (\Throwable $e) {
            $errOutput->writeln($e->getMessage());
        }

        return self::SUCCESS;
    }
}
