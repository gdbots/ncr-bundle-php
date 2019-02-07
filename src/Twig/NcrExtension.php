<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Twig;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\NcrCache;
use Gdbots\Ncr\NcrPreloader;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageRef;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Schemas\Ncr\Enum\NodeStatus;
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
            new \Twig_SimpleFunction('ncr_is_node_published', [$this, 'isNodePublished']),
            new \Twig_SimpleFunction('ncr_preload_node', [$this, 'preloadNode']),
            new \Twig_SimpleFunction('ncr_preload_nodes', [$this, 'preloadNodes']),
            new \Twig_SimpleFunction('ncr_preload_embedded_nodes', [$this, 'preloadEmbeddedNodes']),
            new \Twig_SimpleFunction('ncr_to_node_ref', [$this, 'toNodeRef']),
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
                    ClassUtils::getShortName($e)
                ),
                ['exception' => $e, 'node_ref' => (string)$nodeRef]
            );
        }

        return null;
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isNodePublished($node): bool
    {
        if (!$node instanceof Node) {
            return false;
        }

        return NodeStatus::PUBLISHED()->equals($node->get('status'));
    }

    /**
     * @param bool $andClear
     *
     * @return Node[]
     */
    public function getPreloadedNodes(bool $andClear = true): array
    {
        $nodes = $this->ncrPreloader->getNodes();
        if ($andClear) {
            $this->ncrPreloader->clear();
        }

        return $nodes;
    }

    /**
     * @param bool $andClear
     *
     * @return Node[]
     */
    public function getPreloadedPublishedNodes(bool $andClear = true): array
    {
        $nodes = $this->ncrPreloader->getPublishedNodes();
        if ($andClear) {
            $this->ncrPreloader->clear();
        }

        return $nodes;
    }

    /**
     * Preloads a node so it can optionally be rendered later.
     *
     * @param NodeRef|MessageRef|string $ref
     */
    public function preloadNode($ref): void
    {
        $nodeRef = $this->toNodeRef($ref);
        if (!$nodeRef instanceof NodeRef) {
            return;
        }

        $this->ncrPreloader->addNodeRef($nodeRef);
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
     * @param Message[] $messages Array of messages to extract NodeRefs from.
     * @param array     $paths    An associative array of ['field_name' => 'qname'], i.e. ['user_id', 'acme:user']
     */
    public function preloadEmbeddedNodes(array $messages, array $paths = []): void
    {
        $this->ncrPreloader->addEmbeddedNodeRefs($messages, $paths);
    }

    /**
     * @param mixed $val
     *
     * @return NodeRef
     */
    public function toNodeRef($val): ?NodeRef
    {
        if ($val instanceof NodeRef) {
            return $val;
        } else if (empty($val)) {
            return null;
        } else if ($val instanceof MessageRef) {
            return NodeRef::fromMessageRef($val);
        } else if ($val instanceof Node) {
            return NodeRef::fromNode($val);
        } else if ($val instanceof Identifier && method_exists($val, 'toNodeRef')) {
            return $val->toNodeRef();
        }

        try {
            return NodeRef::fromString((string)$val);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
