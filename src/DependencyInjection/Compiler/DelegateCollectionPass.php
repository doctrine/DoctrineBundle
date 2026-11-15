<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

/** @internal */
final class DelegateCollectionPass implements CompilerPassInterface
{
    public const string TAG = 'doctrine.orm.delegate_collection';

    public function process(ContainerBuilder $container): void
    {
        // when ORM is not enabled, or older than 3.9
        if (! $container->hasDefinition('doctrine.orm.container_delegate_collection_factory')) {
            return;
        }

        $collections = [];
        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $tags) {
            // Each entity needs its own instance: the ORM stores the managed collection in it
            $definition = $container->getDefinition($id)->setShared(false);
            $class      = $container->getReflectionClass($definition->getClass(), false);

            if ($class === null) {
                throw new InvalidArgumentException(sprintf('The class of the "%s" service, tagged "%s", cannot be found.', $id, self::TAG));
            }

            $collections[$class->name] = new Reference($id);
        }

        $container->getDefinition('doctrine.orm.container_delegate_collection_factory')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $collections));
    }
}
