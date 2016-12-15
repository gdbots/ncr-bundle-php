<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Command;

use Gdbots\Ncr\Ncr;
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
The <info>%command.name%</info> command will describe the storage for the NCR.  If a SchemaQName is not 
provided it will run on all schemas having the mixin "gdbots:ncr:mixin:node".

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
        $qname = $input->getArgument('qname');

        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);
        $io->title('NCR Storage Describer');

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

        /** @var Ncr $ncr */
        $ncr = $container->get('ncr');

        foreach ($schemas as $schema) {
            $qname = $schema->getQName();

            try {
                $details = $ncr->describeStorage($qname, $hints);
                $io->success(sprintf('Describing storage for "%s".', $qname));
                $io->comment(sprintf('hints: %s', json_encode($hints)));
                $io->text($details);
                $io->newLine();
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to describe storage for "%s".', $qname));
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
