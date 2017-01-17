<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateStorageCommand extends ContainerAwareCommand
{
    use NcrAwareCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:create-storage')
            ->setDescription('Creates the NCR storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the NCR.  If a SchemaQName is not 
provided it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

<info>php %command.full_name% --hints='{"tenant_id":"client1"}' 'acme:article'</info>

EOF
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any schemas that fail to create.'
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
        $skipErrors = $input->getOption('skip-errors');
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        $io = new SymfonyStyle($input, $output);
        $io->title('NCR Storage Creator');
        $ncr = $this->getNcr();

        foreach ($this->getSchemas(NodeV1Mixin::create(), $input->getArgument('qname')) as $schema) {
            $qname = $schema->getQName();

            try {
                $ncr->createStorage($qname, $hints);
                $io->success(sprintf('Created storage for "%s".', $qname));
            } catch (\Exception $e) {
                if (!$skipErrors) {
                    throw $e;
                }

                $io->error(sprintf('Failed to create storage for "%s".', $qname));
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
