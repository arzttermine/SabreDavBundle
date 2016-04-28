<?php
namespace Arzttermine\SabreDavBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class PluginPass
 */
class PluginPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $serverDefinition = $container->getDefinition('sabredav.server');
        foreach ($container->findTaggedServiceIds('sabredav.plugin') as $id => $attr) {
            $serverDefinition->addMethodCall('addPlugin', array(new Reference($id)));
        }
    }
}
