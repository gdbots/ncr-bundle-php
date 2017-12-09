<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\NcrSearch;
use Gdbots\Schemas\Ncr\Mixin\Indexed\IndexedV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DescribeSearchStorageCommand extends ContainerAwareCommand
{
    use NcrCommandTrait;

    /**
     * @param NcrSearch $ncrSearch
     */
    public function __construct(NcrSearch $ncrSearch)
    {
        parent::__construct();
        $this->ncrSearch = $ncrSearch;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:describe-search-storage')
            ->setDescription('Describes the NcrSearch storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will describe the storage for the NcrSearch.  
If a SchemaQName is not provided it will run on all schemas having the mixin "gdbots:ncr:mixin:indexed".

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

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     *
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = json_decode($input->getOption('context') ?: '{}', true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('NcrSearch Storage Describer');

        foreach ($this->getSchemasUsingMixin(IndexedV1Mixin::create(), $input->getArgument('qname')) as $schema) {
            $qname = $schema->getQName();

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
    }
}
