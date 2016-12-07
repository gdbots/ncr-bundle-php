<?php

namespace Gdbots\Bundle\NcrBundle\Twig;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NcrExtension extends \Twig_Extension
{
    /** @var ContainerInterface */
    protected $container;

    /** @var LoggerInterface */
    protected $logger;

    /** @var bool */
    protected $debug = false;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface|null $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger ?: new NullLogger();
        $this->debug = $container->getParameter('kernel.debug');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            //new \Twig_SimpleFunction('ncr_load', [$this, 'loadNode']),
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gdbots_ncr_extension';
    }

}
