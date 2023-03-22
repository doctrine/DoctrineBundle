<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;
use function assert;
use function in_array;
use function is_a;
use function trigger_deprecation;

/** @internal  */
final class CacheCompatibilityPass implements CompilerPassInterface
{
    private const CONFIGURATION_TAG          = 'doctrine.orm.configuration';
    private const CACHE_METHODS_PSR6_SUPPORT = [
        'setMetadataCache',
        'setQueryCache',
        'setResultCache',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::CONFIGURATION_TAG)) as $id) {
            foreach ($container->getDefinition($id)->getMethodCalls() as $methodCall) {
                if ($methodCall[0] === 'setSecondLevelCacheConfiguration') {
                    $this->updateSecondLevelCache($container, $methodCall[1][0]);
                    continue;
                }

                if (! in_array($methodCall[0], self::CACHE_METHODS_PSR6_SUPPORT, true)) {
                    continue;
                }

                $aliasId      = (string) $methodCall[1][0];
                $definitionId = (string) $container->getAlias($aliasId);

                $this->wrapIfNecessary($container, $aliasId, $definitionId);
            }
        }
    }

    private function updateSecondLevelCache(ContainerBuilder $container, Definition $slcConfigDefinition): void
    {
        foreach ($slcConfigDefinition->getMethodCalls() as $methodCall) {
            if ($methodCall[0] !== 'setCacheFactory') {
                continue;
            }

            $factoryDefinition = $methodCall[1][0];
            assert($factoryDefinition instanceof Definition);
            $aliasId = (string) $factoryDefinition->getArgument(1);
            $this->wrapIfNecessary($container, $aliasId, (string) $container->getAlias($aliasId));
            foreach ($factoryDefinition->getMethodCalls() as $factoryMethodCall) {
                if ($factoryMethodCall[0] !== 'setRegion') {
                    continue;
                }

                $regionDefinition = $container->getDefinition($factoryMethodCall[1][0]);

                // Get inner service for FileLock
                if ($regionDefinition->getClass() === '%doctrine.orm.second_level_cache.filelock_region.class%') {
                    $regionDefinition = $container->getDefinition($regionDefinition->getArgument(0));
                }

                // We don't know how to adjust custom region classes
                if ($regionDefinition->getClass() !== '%doctrine.orm.second_level_cache.default_region.class%') {
                    continue;
                }

                $driverId = (string) $regionDefinition->getArgument(1);
                if (! $container->hasAlias($driverId)) {
                    continue;
                }

                $this->wrapIfNecessary($container, $driverId, (string) $container->getAlias($driverId));
            }

            break;
        }
    }

    private function createCompatibilityLayerDefinition(ContainerBuilder $container, string $definitionId): ?Definition
    {
        $definition = $container->getDefinition($definitionId);

        while (! $definition->getClass() && $definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
        }

        if (is_a($definition->getClass(), CacheItemPoolInterface::class, true)) {
            return null;
        }

        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.4',
            'Configuring doctrine/cache is deprecated. Please update the cache service "%s" to use a PSR-6 cache.',
            $definitionId
        );

        return (new Definition(CacheItemPoolInterface::class))
            ->setFactory([CacheAdapter::class, 'wrap'])
            ->addArgument(new Reference($definitionId));
    }

    private function wrapIfNecessary(ContainerBuilder $container, string $aliasId, string $definitionId): void
    {
        $compatibilityLayer = $this->createCompatibilityLayerDefinition($container, $definitionId);
        if ($compatibilityLayer === null) {
            return;
        }

        $compatibilityLayerId = $definitionId . '.compatibility_layer';
        $container->setAlias($aliasId, $compatibilityLayerId);
        $container->setDefinition($compatibilityLayerId, $compatibilityLayer);
    }
}
