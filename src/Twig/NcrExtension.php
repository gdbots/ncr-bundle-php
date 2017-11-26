<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle\Twig;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Ncr\Exception\NodeNotFound;
use Gdbots\Ncr\NcrCache;
use Gdbots\Pbj\MessageRef;
use Gdbots\Schemas\Ncr\Mixin\Node\Node;
use Gdbots\Schemas\Ncr\NodeRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class NcrExtension extends \Twig_Extension
{
    /** @var NcrCache */
    private $ncrCache;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $debug = false;

    /**
     * @param NcrCache        $ncrCache
     * @param LoggerInterface $logger
     * @param bool            $debug
     */
    public function __construct(NcrCache $ncrCache, ?LoggerInterface $logger = null, bool $debug = false)
    {
        $this->ncrCache = $ncrCache;
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
                $nodeRef = NodeRef::fromString((string)$nodeRef);
            }

            return $this->ncrCache->getNode($nodeRef);
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
                ['exception' => $e, 'node_ref' => (string)$nodeRef]
            );
        }

        return null;
    }
}
