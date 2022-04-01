<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function in_array;
use function is_subclass_of;
use function sprintf;

final class MiddlewaresPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.connections')) {
            return;
        }

        $middlewareAbstractDefs = [];
        $middlewareConnections  = [];
        foreach ($container->findTaggedServiceIds('doctrine.middleware') as $id => $tags) {
            $middlewareAbstractDefs[$id] = $container->getDefinition($id);
            // When a def has doctrine.middleware tags with connection attributes equal to connection names
            // registration of this middleware is limited to the connections with these names
            foreach ($tags as $tag) {
                if (! isset($tag['connection'])) {
                    continue;
                }

                $middlewareConnections[$id][] = $tag['connection'];
            }
        }

        foreach (array_keys($container->getParameter('doctrine.connections')) as $name) {
            $middlewareDefs = [];
            foreach ($middlewareAbstractDefs as $id => $abstractDef) {
                if (isset($middlewareConnections[$id]) && ! in_array($name, $middlewareConnections[$id], true)) {
                    continue;
                }

                $middlewareDefs[] = $childDef = $container->setDefinition(
                    sprintf('%s.%s', $id, $name),
                    new ChildDefinition($id)
                );

                if (! is_subclass_of($abstractDef->getClass(), ConnectionNameAwareInterface::class)) {
                    continue;
                }

                $childDef->addMethodCall('setConnectionName', [$name]);
            }

            $container
                ->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))
                ->addMethodCall('setMiddlewares', [$middlewareDefs]);
        }
    }
}
