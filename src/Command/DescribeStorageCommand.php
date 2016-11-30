<?php

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Repository\CanCreateRepositoryStorage;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DescribeStorageCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ncr:describe-storage')
            ->setDescription('Describes the NCR storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will describe the storage for the NCR.  If a curie is not provided
it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

Each node schema (e.g. article, video, photo) can potentially have its own storage.

<info>php %command.full_name% --hints='{"tenant_id":"client1"}' 'acme:article'</info>

EOF
            )
            ->addOption(
                'service-id-template',
                null,
                InputOption::VALUE_REQUIRED,
                'The repository service id is derived from the qname using this template.',
                '%vendor%_%message%_repository'
            )
            ->addOption(
                'hints',
                null,
                InputOption::VALUE_REQUIRED,
                'Hints to provide to the repository describer (json).'
            )
            ->addArgument(
                'qname',
                InputArgument::OPTIONAL,
                'The qname of the node schema. e.g. "acme:article"'
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
        $serviceIdTemplate = $input->getOption('service-id-template');
        $hints = json_decode($input->getOption('hints') ?: '{}', true);
        $curie = $input->getArgument('curie');

        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);
        $io->title('Node Repository Storage Describer');

        if (null === $curie) {
            $schemas = MessageResolver::findAllUsingMixin(NodeV1Mixin::create());
        } else {
            /** @var Message $class */
            $class = MessageResolver::resolveCurie(SchemaCurie::fromString($curie));
            $schema = $class::schema();
            if (!$schema->hasMixin(NodeV1Mixin::create()->getId()->getCurieMajor())) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The curie [%s] does not have mixin [%s].',
                        $curie,
                        NodeV1Mixin::create()->getId()->getCurieMajor()
                    )
                );
            }

            $schemas = [$schema];
        }

        foreach ($schemas as $schema) {
            $curie = $schema->getCurie();
            $serviceId = str_replace(
                ['%vendor%', '%package%', '%message%'],
                [$curie->getVendor(), $curie->getPackage(), $curie->getMessage()],
                $serviceIdTemplate
            );

            if (!$container->has($serviceId)) {
                $io->note(sprintf('Repository service for "%s" doesn\'t exist at "%s".', $curie, $serviceId));
                continue;
            }

            $repository = $container->get($serviceId);
            if (!$repository instanceof CanCreateRepositoryStorage) {
                $io->note(sprintf('Repository service for "%s" doesn\'t implement CanCreateRepositoryStorage.', $curie));
                continue;
            }

            try {
                $details = $repository->describeRepositoryStorage($hints);
                $io->success(sprintf('Describing storage for "%s".', $curie));
                $io->comment(sprintf('hints: %s', json_encode($hints)));
                $io->text($details);
                $io->newLine();
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to describe storage for "%s".', $curie));
                $io->text(get_class($e));
                $io->text($e->getMessage());
                $io->newLine();
            }
        }
    }
}
