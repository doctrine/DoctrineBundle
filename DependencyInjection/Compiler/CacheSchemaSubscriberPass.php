<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects Doctrine DBAL and legacy PDO adapters into their schema subscribers.
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
        $this->injectAdapters($container, 'doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_subscriber', DoctrineDbalAdapter::class);

        // available in Symfony 5.1 and up to Symfony 5.4 (deprecated)
        $this->injectAdapters($container, 'doctrine.orm.listeners.pdo_cache_adapter_doctrine_schema_subscriber', PdoAdapter::class);
    }

    private function injectAdapters(ContainerBuilder $container, string $subscriberId, string $class)
    {
        if (! $container->hasDefinition($subscriberId)) {
            return;
        }

        $subscriber = $container->getDefinition($subscriberId);

        $cacheAdaptersReferences = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            if ($definition->getClass() !== $class) {
                continue;
            }

            $cacheAdaptersReferences[] = new Reference($id);
        }

        $subscriber->replaceArgument(0, $cacheAdaptersReferences);
    }
}
