<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function is_subclass_of;
use function sprintf;
use function usort;

/** @internal */
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
            $middlewareRefs = [];
            $i              = 0;
            foreach ($middlewareAbstractDefs as $id => $abstractDef) {
                if (isset($middlewareConnections[$id]) && ! array_key_exists($name, $middlewareConnections[$id])) {
                    continue;
                }

                $childDef    = $container->setDefinition(
                    $childId = sprintf('%s.%s', $id, $name),
                    (new ChildDefinition($id))
                        ->setTags($abstractDef->getTags())->clearTag('doctrine.middleware')
                        ->setAutoconfigured($abstractDef->isAutoconfigured())
                        ->setAutowired($abstractDef->isAutowired()),
                );
                $middlewareRefs[$id] = [new Reference($childId), ++$i];

                if (! is_subclass_of($abstractDef->getClass(), ConnectionNameAwareInterface::class)) {
                    continue;
                }

                $childDef->addMethodCall('setConnectionName', [$name]);
            }

            $middlewareRefs = array_map(
                static fn (string $id, array $ref) => [
                    $middlewareConnections[$id][$name] ?? $middlewarePriorities[$id] ?? 0,
                    $ref[1],
                    $ref[0],
                ],
                array_keys($middlewareRefs),
                array_values($middlewareRefs),
            );
            usort($middlewareRefs, static fn (array $a, array $b): int => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
            $middlewareRefs = array_map(static fn (array $value): Reference => $value[2], $middlewareRefs);

            $container
                ->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))
                ->addMethodCall('setMiddlewares', [$middlewareRefs]);
        }
    }
}
