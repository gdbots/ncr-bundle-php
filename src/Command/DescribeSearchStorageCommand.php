<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Schemas\Ncr\Mixin\Indexed\IndexedV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DescribeSearchStorageCommand extends ContainerAwareCommand
{
    use NcrAwareCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:describe-search-storage')
            ->setDescription('Describes the NCR Search storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will describe the search storage for the NCR.
If a SchemaQName is not provided it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

<info>php %command.full_name% --hints='{"tenant_id":"client1"}' 'acme:article'</info>

EOF
            )
            ->addOption(
                'hints',
                null,
                InputOption::VALUE_REQUIRED,
                'Hints to provide to the NCR (json).'
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        $io = new SymfonyStyle($input, $output);
        $io->title('NCR Search Storage Describer');
        $ncrSearch = $this->getNcrSearch();

        foreach ($this->getSchemas(IndexedV1Mixin::create(), $input->getArgument('qname')) as $schema) {
            $qname = $schema->getQName();

            try {
                $details = $ncrSearch->describeStorage($qname, $hints);
                $io->success(sprintf('Describing search storage for "%s".', $qname));
                $io->comment(sprintf('hints: %s', json_encode($hints)));
                $io->text($details);
                $io->newLine();
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to describe search storage for "%s".', $qname));
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
