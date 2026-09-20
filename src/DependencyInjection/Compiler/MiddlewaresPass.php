<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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
        $everyConnection        = [];
        foreach ($container->findTaggedServiceIds('doctrine.middleware') as $id => $tags) {
            $middlewareAbstractDefs[$id] = $container->getDefinition($id);
            // When a def has doctrine.middleware tags with connection attributes equal to connection names
            // registration of this middleware is limited to the connections with these names
            foreach ($tags as $tag) {
                if (! isset($tag['connection'])) {
                    $everyConnection[$id] = true;

                    if (isset($tag['priority']) && ! isset($middlewarePriorities[$id])) {
                        $middlewarePriorities[$id] = $tag['priority'];
                    }

                    continue;
                }

                $middlewareConnections[$id][$tag['connection']] = $tag['priority'] ?? null;
            }
        }

        // Middlewares listed per connection in the configuration join the maps above, so they go
        // through the priority handling and the ConnectionNameAwareInterface support below.
        $configuredPriorities = [];
        foreach ($this->configuredMiddlewares($container) as $name => $entries) {
            foreach ($entries as $entry) {
                $id = $entry['service'];

                if (! $container->hasDefinition($id) && ! $container->hasAlias($id)) {
                    throw new InvalidArgumentException(sprintf(
                        'The service "%s" listed in the middlewares of the "%s" connection does not exist.',
                        $id,
                        $name,
                    ));
                }

                $middlewareAbstractDefs[$id]  ??= $container->findDefinition($id);
                $configuredPriorities[$id][$name] = $entry['priority'];

                // A middleware tagged without a connection applies everywhere, and listing it here
                // must not take it away from the other ones: only the priority above is kept.
                if (isset($everyConnection[$id])) {
                    continue;
                }

                $middlewareConnections[$id][$name] ??= null;
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

                $class = $abstractDef->getClass();
                if ($class === null || ! is_subclass_of($class, ConnectionNameAwareInterface::class)) {
                    continue;
                }

                $childDef->addMethodCall('setConnectionName', [$name]);
            }

            $middlewareRefs = array_map(
                static fn (string $id, array $ref) => [
                    $configuredPriorities[$id][$name] ?? $middlewareConnections[$id][$name] ?? $middlewarePriorities[$id] ?? 0,
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

    /** @return array<string, list<array{service: string, priority: int|null}>> */
    private function configuredMiddlewares(ContainerBuilder $container): array
    {
        if (! $container->hasParameter('doctrine.dbal.connection_middlewares')) {
            return [];
        }

        /** @var array<string, list<array{service: string, priority: int|null}>> $middlewares */
        $middlewares = $container->getParameter('doctrine.dbal.connection_middlewares');

        return $middlewares;
    }
}
