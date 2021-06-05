<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;
use function assert;
use function is_a;
use function trigger_deprecation;

/** @internal  */
final class CacheCompatibilityPass implements CompilerPassInterface
{
    private const CONFIGURATION_TAG              = 'doctrine.orm.configuration';
    private const CACHE_METHODS_PSR6_SUPPORT_MAP = [
        'setMetadataCache' => true,
        'setQueryCacheImpl' => false,
        'setResultCacheImpl' => false,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::CONFIGURATION_TAG)) as $id) {
            foreach ($container->getDefinition($id)->getMethodCalls() as $methodCall) {
                if ($methodCall[0] === 'setSecondLevelCacheConfiguration') {
                    $this->updateSecondLevelCache($container, $methodCall[1][0]);
                    continue;
                }

                if (! isset(self::CACHE_METHODS_PSR6_SUPPORT_MAP[$methodCall[0]])) {
                    continue;
                }

                $aliasId      = (string) $methodCall[1][0];
                $definitionId = (string) $container->getAlias($aliasId);
                $shouldBePsr6 = self::CACHE_METHODS_PSR6_SUPPORT_MAP[$methodCall[0]];

                $this->wrapIfNecessary($container, $aliasId, $definitionId, $shouldBePsr6);
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
            $this->wrapIfNecessary($container, $aliasId, (string) $container->getAlias($aliasId), false);
            break;
        }
    }

    private function createCompatibilityLayerDefinition(ContainerBuilder $container, string $definitionId, bool $shouldBePsr6): ?Definition
    {
        $definition = $container->getDefinition($definitionId);

        while (! $definition->getClass() && $definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
        }

        if ($shouldBePsr6 === is_a($definition->getClass(), CacheItemPoolInterface::class, true)) {
            return null;
        }

        $targetClass   = CacheProvider::class;
        $targetFactory = DoctrineProvider::class;

        if ($shouldBePsr6) {
            $targetClass   = CacheItemPoolInterface::class;
            $targetFactory = CacheAdapter::class;

            trigger_deprecation(
                'doctrine/doctrine-bundle',
                '2.4',
                'Configuring doctrine/cache is deprecated. Please update the cache service "%s" to use a PSR-6 cache.',
                $definitionId
            );
        }

        return (new Definition($targetClass))
            ->setFactory([$targetFactory, 'wrap'])
            ->addArgument(new Reference($definitionId));
    }

    private function wrapIfNecessary(ContainerBuilder $container, string $aliasId, string $definitionId, bool $shouldBePsr6): void
    {
        $compatibilityLayer = $this->createCompatibilityLayerDefinition($container, $definitionId, $shouldBePsr6);
        if ($compatibilityLayer === null) {
            return;
        }

        $compatibilityLayerId = $definitionId . '.compatibility_layer';
        $container->setAlias($aliasId, $compatibilityLayerId);
        $container->setDefinition($compatibilityLayerId, $compatibilityLayer);
    }
}
