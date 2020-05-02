<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects PdoAdapter into its schema subscriber.
 *
 * Must be run later after ResolveChildDefinitionsPass.
 */
class CacheSchemaSubscriberPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $subscriberId = 'doctrine.orm.listeners.pdo_cache_adapter_doctrine_schema_subscriber';

        if (! $container->hasDefinition($subscriberId)) {
            return;
        }

        $cacheAdaptersReferences = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            if ($definition->getClass() !== PdoAdapter::class) {
                continue;
            }

            $cacheAdaptersReferences[] = new Reference($id);
        }

        $container->getDefinition($subscriberId)
            ->replaceArgument(0, $cacheAdaptersReferences);
    }
}
