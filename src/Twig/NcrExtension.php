<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\NcrBundle\Twig;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\NcrCache;
use Gdbots\Pbj\MessageRef;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class NcrExtension extends \Twig_Extension
{
    /** @var ContainerInterface */
    private $container;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $debug = false;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
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
            new \Twig_SimpleFunction('ncr_get_node', [$this, 'getNode']),
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gdbots_ncr_extension';
    }

    /**
     * Gets a node from the NcrCache service if it's available.
     * This will NOT make a new request to fetch a node, it must
     * have already been loaded to NcrCache.
     *
     * @param NodeRef|MessageRef|string $ref
     *
     * @return Node|null
     *
     * @throws \Exception
     */
    public function getNode($ref): ?Node
    {
        if (empty($ref)) {
            return null;
        }

        if ($ref instanceof MessageRef) {
            $nodeRef = NodeRef::fromMessageRef($ref);
        } else {
            $nodeRef = $ref;
        }

        try {
            if (!$nodeRef instanceof NodeRef) {
                $nodeRef = NodeRef::fromString((string) $nodeRef);
            }

            return $this->getNcrCache()->getNode($nodeRef);
        } catch (NodeNotFound $e) {
            return null;
        } catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->error(
                sprintf(
                    '%s::Unable to process twig "ncr_get_node" function for [{node_ref}].',
                    ClassUtils::getShortName($e)
                ),
                ['exception' => $e, 'node_ref' => (string) $nodeRef]
            );
        }

        return null;
    }

    /**
     * @return NcrCache
     */
    private function getNcrCache(): NcrCache
    {
        return $this->container->get('ncr_cache');
    }
}
