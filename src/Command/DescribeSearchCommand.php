<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Bundle\PbjxBundle\Command\PbjxAwareCommandTrait;
use Gdbots\Ncr\NcrSearch;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DescribeSearchCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'ncr:describe-search';
    protected NcrSearch $ncrSearch;

    public function __construct(ContainerInterface $container, NcrSearch $ncrSearch)
    {
        $this->container = $container;
        $this->ncrSearch = $ncrSearch;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_ncr.ncr_search.provider');

        $this
            ->setDescription("Describes the NcrSearch ({$provider}) storage")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will describe the storage for the NcrSearch ({$provider}).
If a SchemaQName is not provided it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                'Context to provide to the NcrSearch (json).'
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
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('NcrSearch Storage Describer');
        $io->comment('context: ' . json_encode($context));

        $qnames = $input->getArgument('qname')
            ? [SchemaQName::fromString($input->getArgument('qname'))]
            : array_map(
                fn(string $curie) => SchemaCurie::fromString($curie)->getQName(),
                MessageResolver::findAllUsingMixin(NodeV1Mixin::SCHEMA_CURIE_MAJOR, false)
            );

        foreach ($qnames as $qname) {
            try {
                $details = $this->ncrSearch->describeStorage($qname, $context);
                $io->success(sprintf('Describing NcrSearch storage for "%s".', $qname));
                $io->comment(sprintf('context: %s', json_encode($context)));
                $io->text($details);
                $io->newLine();
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to describe NcrSearch storage for "%s".', $qname));
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
