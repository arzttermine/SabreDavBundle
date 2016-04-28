<?php
namespace Arzttermine\SabreDavBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;


use Arzttermine\SabreDavBundle\DependencyInjection\Compiler\CollectionPass;
use Arzttermine\SabreDavBundle\DependencyInjection\Compiler\PluginPass;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ArzttermineSabreDavBundle.
 */
class ArzttermineSabreDavBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new CollectionPass());
        $container->addCompilerPass(new PluginPass());
    }
}

