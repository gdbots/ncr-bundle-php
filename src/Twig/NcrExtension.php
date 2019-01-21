<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Twig;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrPreloader;
use Gdbots\Pbj\MessageRef;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class NcrExtension extends \Twig_Extension
{
    /** @var NcrCache */
    private $ncrCache;

    /** @var NcrPreloader */
    private $ncrPreloader;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $debug = false;

    /**
     * @param NcrCache        $ncrCache
     * @param NcrPreloader    $ncrPreloader
     * @param LoggerInterface $logger
     * @param bool            $debug
     */
    public function __construct(
        NcrCache $ncrCache,
        NcrPreloader $ncrPreloader,
        ?LoggerInterface $logger = null,
        bool $debug = false
    ) {
        $this->ncrCache = $ncrCache;
        $this->ncrPreloader = $ncrPreloader;
        $this->logger = $logger ?: new NullLogger();
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('ncr_get_node', [$this, 'getNode']),
            new \Twig_SimpleFunction('ncr_get_preloaded_nodes', [$this, 'getPreloadedNodes']),
            new \Twig_SimpleFunction('ncr_get_preloaded_published_nodes', [$this, 'getPreloadedPublishedNodes']),
            new \Twig_SimpleFunction('ncr_preload_node', [$this, 'preloadNode']),
            new \Twig_SimpleFunction('ncr_preload_nodes', [$this, 'preloadNodes']),
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
     * @return Node
     *
     * @throws \Throwable
     */
    public function getNode($ref): ?Node
    {
        $nodeRef = $this->refToNodeRef($ref);
        if (!$nodeRef instanceof NodeRef) {
            return null;
        }

        try {
            return $this->ncrCache->getNode($nodeRef);
        } catch (NodeNotFound $e) {
            return null;
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->error(
                sprintf(
                    '%s::Unable to process twig "ncr_get_node" function for [{node_ref}].',
                    ClassUtils::getShortName($e)
                ),
                ['exception' => $e, 'node_ref' => (string)$nodeRef]
            );
        }

        return null;
    }

    /**
     * @return Node[]
     */
    public function getPreloadedNodes(): array
    {
        return $this->ncrPreloader->getNodes();
    }

    /**
     * @return Node[]
     */
    public function getPreloadedPublishedNodes(): array
    {
        return $this->ncrPreloader->getPublishedNodes();
    }

    /**
     * Preloads a node so it can optionally be rendered later.
     *
     * @param NodeRef|MessageRef|string $ref
     */
    public function preloadNode($ref): void
    {
        $nodeRef = $this->refToNodeRef($ref);
        if (!$nodeRef instanceof NodeRef) {
            return;
        }

        try {
            $this->ncrPreloader->addNodeRef($nodeRef);
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->error(
                sprintf(
                    '%s::Unable to process twig "ncr_preload_node" function for [{node_ref}].',
                    ClassUtils::getShortName($e)
                ),
                ['exception' => $e, 'node_ref' => (string)$ref]
            );
        }
    }

    /**
     * @param NodeRef[]|MessageRef[]|string[] $refs
     */
    public function preloadNodes(array $refs = []): void
    {
        foreach ($refs as $ref) {
            $this->preloadNode($ref);
        }
    }

    /**
     * @param mixed $ref
     *
     * @return NodeRef
     */
    private function refToNodeRef($ref): ?NodeRef
    {
        if ($ref instanceof NodeRef) {
            return $ref;
        }

        if (empty($ref)) {
            return null;
        }

        if ($ref instanceof MessageRef) {
            return NodeRef::fromMessageRef($ref);
        }

        try {
            return NodeRef::fromString((string)$ref);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
