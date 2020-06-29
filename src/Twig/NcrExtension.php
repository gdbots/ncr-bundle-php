<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Twig;

use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrPreloader;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\WellKnown\MessageRef;
use Gdbots\Pbj\WellKnown\NodeRef;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
use Gdbots\Schemas\Ncr\Mixin\Node\NodeV1Mixin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NcrExtension extends AbstractExtension
{
    private NcrCache $ncrCache;
    private NcrPreloader $ncrPreloader;
    private LoggerInterface $logger;
    private bool $debug;

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

    public function getFunctions()
    {
        return [
            new TwigFunction('ncr_deref_nodes', [$this, 'derefNodes']),
            new TwigFunction('ncr_get_node', [$this, 'getNode']),
            new TwigFunction('ncr_get_preloaded_nodes', [$this, 'getPreloadedNodes']),
            new TwigFunction('ncr_get_preloaded_published_nodes', [$this, 'getPreloadedPublishedNodes']),
            new TwigFunction('ncr_is_node_published', [$this, 'isNodePublished']),
            new TwigFunction('ncr_preload_node', [$this, 'preloadNode']),
            new TwigFunction('ncr_preload_nodes', [$this, 'preloadNodes']),
            new TwigFunction('ncr_preload_embedded_nodes', [$this, 'preloadEmbeddedNodes']),
            new TwigFunction('ncr_to_node_ref', [$this, 'toNodeRef']),
        ];
    }

    /**
     * @param Message $node
     * @param array   $fields
     * @param string  $return
     *
     * @return array
     *
     * @see NcrCache::derefNodes
     */
    public function derefNodes($node, array $fields = [], ?string $return = null): array
    {
        if (!$node instanceof Message) {
            return [];
        }

        return $this->ncrCache->derefNodes($node, $fields, $return);
    }

    /**
     * Gets a node from the NcrCache service if it's available.
     * This will NOT make a new request to fetch a node, it must
     * have already been loaded to NcrCache.
     *
     * @param NodeRef|MessageRef|string $ref
     *
     * @return Message
     *
     * @throws \Throwable
     */
    public function getNode($ref): ?Message
    {
        $nodeRef = $this->toNodeRef($ref);
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
                    ClassUtil::getShortName($e)
                ),
                ['exception' => $e, 'node_ref' => (string)$nodeRef]
            );
        }

        return null;
    }

    public function isNodePublished($node): bool
    {
        if (!$node instanceof Message) {
            return false;
        }

        return NodeStatus::PUBLISHED === $node->fget(NodeV1Mixin::STATUS_FIELD);
    }

    /**
     * @param bool   $andClear
     * @param string $namespace
     *
     * @return Message[]
     */
    public function getPreloadedNodes(bool $andClear = true, string $namespace = NcrPreloader::DEFAULT_NAMESPACE): array
    {
        $nodes = $this->ncrPreloader->getNodes(null, $namespace);
        if ($andClear) {
            $this->ncrPreloader->clear($namespace);
        }

        return $nodes;
    }

    /**
     * @param bool   $andClear
     * @param string $namespace
     *
     * @return Message[]
     */
    public function getPreloadedPublishedNodes(bool $andClear = true, string $namespace = NcrPreloader::DEFAULT_NAMESPACE): array
    {
        $nodes = $this->ncrPreloader->getPublishedNodes($namespace);
        if ($andClear) {
            $this->ncrPreloader->clear($namespace);
        }

        return $nodes;
    }

    /**
     * Preloads a node so it can optionally be rendered later.
     *
     * @param NodeRef|MessageRef|string $ref
     * @param string                    $namespace
     */
    public function preloadNode($ref, string $namespace = NcrPreloader::DEFAULT_NAMESPACE): void
    {
        $nodeRef = $this->toNodeRef($ref);
        if (!$nodeRef instanceof NodeRef) {
            return;
        }

        $this->ncrPreloader->addNodeRef($nodeRef, $namespace);
    }

    /**
     * @param NodeRef[]|MessageRef[]|string[] $refs
     * @param string                          $namespace
     */
    public function preloadNodes(array $refs = [], string $namespace = NcrPreloader::DEFAULT_NAMESPACE): void
    {
        foreach ($refs as $ref) {
            $this->preloadNode($ref, $namespace);
        }
    }

    /**
     * @param Message[] $messages Array of messages to extract NodeRefs from.
     * @param array     $paths    An associative array of ['field_name' => 'qname'], i.e. ['user_id', 'acme:user']
     * @param string    $namespace
     *
     * @see NcrPreloader::addEmbeddedNodeRefs
     *
     */
    public function preloadEmbeddedNodes(
        array $messages,
        array $paths = [],
        string $namespace = NcrPreloader::DEFAULT_NAMESPACE
    ): void {
        $this->ncrPreloader->addEmbeddedNodeRefs($messages, $paths, $namespace);
    }

    public function toNodeRef($val): ?NodeRef
    {
        if ($val instanceof NodeRef) {
            return $val;
        } else if (empty($val)) {
            return null;
        } else if ($val instanceof Message) {
            return $val->generateNodeRef();
        } else if ($val instanceof MessageRef) {
            return NodeRef::fromMessageRef($val);
        }

        try {
            return NodeRef::fromString((string)$val);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
