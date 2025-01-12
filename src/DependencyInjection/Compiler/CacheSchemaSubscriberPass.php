<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects Doctrine DBAL and legacy PDO adapters into their schema subscribers.
 *
 * Must be run later after ResolveChildDefinitionsPass.
 *
 * @final since 2.9
 */
class CacheSchemaSubscriberPass implements CompilerPassInterface
{
    /** @return void */
    public function process(ContainerBuilder $container)
    {
        if (! $container->hasDefinition('doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener')) {
            return;
        }

        $subscriber = $container->getDefinition('doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener');

        $cacheAdaptersReferences = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            if ($definition->getClass() !== DoctrineDbalAdapter::class) {
                continue;
            }

            $cacheAdaptersReferences[] = new Reference($id);
        }

        $subscriber->replaceArgument(0, $cacheAdaptersReferences);
    }
}
