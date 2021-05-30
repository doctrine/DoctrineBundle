<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;
use function preg_replace;
use function sprintf;

/** @internal */
final class RegisterFastestCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('kernel.debug')) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds(IdGeneratorPass::CONFIGURATION_TAG)) as $id) {
            $entityManagerName               = preg_replace(['/^doctrine.orm./', '/_configuration$/'], '', $id);
            $metadataCacheAlias              = sprintf('doctrine.orm.%s_metadata_cache', $entityManagerName);
            $decoratedMetadataCacheServiceId = (string) $container->getAlias($metadataCacheAlias);
            $phpArrayCacheDecoratorServiceId = $decoratedMetadataCacheServiceId . '.php_array';
            $phpArrayFile                    = '%kernel.cache_dir%' . sprintf('/doctrine/orm/%s_metadata.php', $entityManagerName);
            $cacheWarmerServiceId            = sprintf('doctrine.orm.%s_metadata_cache_warmer', $entityManagerName);

            $container->setAlias($metadataCacheAlias, $phpArrayCacheDecoratorServiceId);
            $container->register($cacheWarmerServiceId, DoctrineMetadataCacheWarmer::class)
                ->setArguments([new Reference(sprintf('doctrine.orm.%s_entity_manager', $entityManagerName)), $phpArrayFile])
                ->addTag('kernel.cache_warmer', ['priority' => 1000]); // priority should be higher than ProxyCacheWarmer
            $container->register($phpArrayCacheDecoratorServiceId, PhpArrayAdapter::class)
                ->addArgument($phpArrayFile)
                ->addArgument(new Reference($decoratedMetadataCacheServiceId));
        }
    }
}
