<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaQName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DescribeStorageCommand extends Command
{
    protected static $defaultName = 'ncr:describe-storage';
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
            ->setDescription("Describes the Ncr ({$provider}) storage")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will describe the storage for the Ncr ({$provider}).
If a SchemaQName is not provided it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

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
            ->addArgument(
                'qname',
                InputArgument::OPTIONAL,
                'The SchemaQName of the node. e.g. "acme:article"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('Ncr Storage Describer');
        $io->comment('context: ' . json_encode($context));

        $qnames = $input->getArgument('qname')
            ? [SchemaQName::fromString($input->getArgument('qname'))]
            : array_map(
                fn(string $curie) => SchemaCurie::fromString($curie)->getQName(),
                MessageResolver::findAllUsingMixin('gdbots:ncr:mixin:node:v1', false)
            );

        foreach ($qnames as $qname) {
            try {
                $details = $this->ncr->describeStorage($qname, $context);
                $io->success(sprintf('Describing Ncr storage for "%s".', $qname));
                $io->comment(sprintf('context: %s', json_encode($context)));
                $io->text($details);
                $io->newLine();
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to describe Ncr storage for "%s".', $qname));
                $io->text($e->getMessage());
                if ($e->getPrevious()) {
                    $io->newLine();
                    $io->text($e->getPrevious()->getMessage());
                }

                $io->newLine();
            }
        }

        return self::SUCCESS;
    }
}
