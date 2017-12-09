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

final class CreateSearchStorageCommand extends ContainerAwareCommand
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
            ->setName('ncr:create-search-storage')
            ->setDescription('Creates the NcrSearch storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the NcrSearch.  
If a SchemaQName is not provided it will run on all schemas having the mixin "gdbots:ncr:mixin:indexed".

<info>php %command.full_name% --tenant-id=client1 'acme:article'</info>

EOF
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any schemas that fail to create.'
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
        $skipErrors = $input->getOption('skip-errors');
        $context = json_decode($input->getOption('context') ?: '{}', true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('NcrSearch Storage Creator');

        foreach ($this->getSchemasUsingMixin(IndexedV1Mixin::create(), $input->getArgument('qname')) as $schema) {
            $qname = $schema->getQName();

            try {
                $this->ncrSearch->createStorage($qname, $context);
                $io->success(sprintf('Created NcrSearch storage for "%s".', $qname));
            } catch (\Throwable $e) {
                if (!$skipErrors) {
                    throw $e;
                }

                $io->error(sprintf('Failed to create NcrSearch storage for "%s".', $qname));
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
