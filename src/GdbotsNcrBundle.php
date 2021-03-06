<?php
declare(strict_types=1);

namespace Gdbots\Bundle\NcrBundle;

use Gdbots\Bundle\NcrBundle\DependencyInjection\Compiler\ValidateNcrPass;
use Gdbots\Bundle\NcrBundle\DependencyInjection\Compiler\ValidateNcrSearchPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GdbotsNcrBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ValidateNcrPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateNcrSearchPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
