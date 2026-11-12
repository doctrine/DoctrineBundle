<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function assert;
use function method_exists;

final class EntityAutoDiscoveryPass implements CompilerPassInterface
{
    public const string TAG = 'doctrine.orm.auto_discover_class_locator';

    public function process(ContainerBuilder $container): void
    {
        $locatorIds = array_keys($container->findTaggedServiceIds(self::TAG));
        /** @phpstan-ignore function.alreadyNarrowedType */
        if ($locatorIds === [] || ! method_exists($container, 'findTaggedResourceIds')) {
            return;
        }

        $classes = [];
        foreach ($container->findTaggedResourceIds('doctrine.orm.entity', false) as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            assert($class !== null);

            $classes[$class] = true;
        }

        foreach ($locatorIds as $locatorId) {
            $container->getDefinition($locatorId)->replaceArgument(0, array_keys($classes));
        }
    }
}
