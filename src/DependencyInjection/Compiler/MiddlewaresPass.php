<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function is_subclass_of;
use function sprintf;
use function uasort;

final class MiddlewaresPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.connections')) {
            return;
        }

        $middlewareAbstractDefs = [];
        $middlewareConnections  = [];
        $middlewarePriorities   = [];
        foreach ($container->findTaggedServiceIds('doctrine.middleware') as $id => $tags) {
            $middlewareAbstractDefs[$id] = $container->getDefinition($id);
            // When a def has doctrine.middleware tags with connection attributes equal to connection names
            // registration of this middleware is limited to the connections with these names
            foreach ($tags as $tag) {
                if (! isset($tag['connection'])) {
                    if (isset($tag['priority']) && ! isset($middlewarePriorities[$id])) {
                        $middlewarePriorities[$id] = $tag['priority'];
                    }

                    continue;
                }

                $middlewareConnections[$id][$tag['connection']] = $tag['priority'] ?? null;
            }
        }

        foreach (array_keys($container->getParameter('doctrine.connections')) as $name) {
            $middlewareDefs = [];
            $i              = 0;
            foreach ($middlewareAbstractDefs as $id => $abstractDef) {
                if (isset($middlewareConnections[$id]) && ! array_key_exists($name, $middlewareConnections[$id])) {
                    continue;
                }

                $middlewareDefs[$id] = [
                    $childDef = $container->setDefinition(
                        sprintf('%s.%s', $id, $name),
                        new ChildDefinition($id),
                    ),
                    ++$i,
                ];

                if (! is_subclass_of($abstractDef->getClass(), ConnectionNameAwareInterface::class)) {
                    continue;
                }

                $childDef->addMethodCall('setConnectionName', [$name]);
            }

            $middlewareDefs = array_map(
                static fn ($id, $def) => [
                    $middlewareConnections[$id][$name] ?? $middlewarePriorities[$id] ?? 0,
                    $def[1],
                    $def[0],
                ],
                array_keys($middlewareDefs),
                array_values($middlewareDefs),
            );
            uasort($middlewareDefs, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
            $middlewareDefs = array_map(static fn ($value) => $value[2], $middlewareDefs);

            $container
                ->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))
                ->addMethodCall('setMiddlewares', [$middlewareDefs]);
        }
    }
}
