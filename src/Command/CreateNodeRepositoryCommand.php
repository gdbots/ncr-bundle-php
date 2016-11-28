<?php

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Repository\NodeRepository;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaQName;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateNodeRepositoryCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:create-node-repository')
            ->setDescription('Creates the node repository storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the node repository.  If a SchemaQName is not 
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
                'Hints to provide to the node repository (json).'
            )
            ->addArgument(
                'qname',
                InputArgument::OPTIONAL,
                'The SchemaQName of the node. e.g. "acme:article"'
            )
        ;
    }

    /**
     * @param InputInterface $input
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
        $qname = $input->getArgument('qname');

        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);
        $io->title('Node Repository Creator');

        if (null === $qname) {
            $schemas = MessageResolver::findAllUsingMixin(NodeV1Mixin::create());
        } else {
            /** @var Message $class */
            $class = MessageResolver::resolveCurie(
                MessageResolver::resolveQName(SchemaQName::fromString($qname))
            );
            $schema = $class::schema();

            if (!$schema->hasMixin(NodeV1Mixin::create()->getId()->getCurieMajor())) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The SchemaQName [%s] does not have mixin [%s].',
                        $qname,
                        NodeV1Mixin::create()->getId()->getCurieMajor()
                    )
                );
            }

            $schemas = [$schema];
        }

        /** @var NodeRepository $repository */
        $repository = $container->get('gdbots_ncr.node_repository.dynamodb');

        foreach ($schemas as $schema) {
            $qname = $schema->getQName();

            try {
                $repository->createStorage($qname, $hints);
                $io->success(sprintf('Created storage for "%s".', $qname));
            } catch (\Exception $e) {
                if (!$skipErrors) {
                    throw $e;
                }

                $io->error(sprintf('Failed to create storage for "%s".', $qname));
                $io->text(get_class($e));
                $io->text($e->getMessage());
                $io->newLine();
            }
        }
    }
}
