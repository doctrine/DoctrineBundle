<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Lets the registry reset decorated entity managers.
 *
 * Decorating an entity manager points its service id at the decorator and makes the entity manager itself private.
 * Resetting the decorator would only rebuild it around the same entity manager instance, so the registry resets
 * the inner one instead, made public since the dumped container only lets the registry fetch and rebuild public services.
 *
 * Must be run after DecoratorServicePass and before the removing passes.
 *
 * @internal
 */
final class DecoratedEntityManagersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.entity_managers') || ! $container->hasDefinition('doctrine')) {
            return;
        }

        $decoratedManagers = [];

        foreach ($container->getParameter('doctrine.entity_managers') as $id) {
            $definitionId = $this->findDefinitionId($container, $id);

            if ($definitionId === null) {
                continue;
            }

            $innerId = $this->findInnermostId($container, $container->getDefinition($definitionId));

            if ($innerId === null) {
                continue;
            }

            $container->getDefinition($innerId)->setPublic(true);

            $decoratedManagers[$id] = $innerId;
        }

        if (! $decoratedManagers) {
            return;
        }

        $container->getDefinition('doctrine')->setArgument(5, $decoratedManagers);
    }

    /** Returns the id of the innermost service wrapped by the given decorator, or null when the definition is not a decorator. */
    private function findInnermostId(ContainerBuilder $container, Definition $definition): string|null
    {
        $innerId = $definition->getTag('container.decorator')[0]['inner'] ?? null;

        if ($innerId === null) {
            return null;
        }

        // The inner service can have been decorated in turn, making its id an alias
        $innerId = $this->findDefinitionId($container, $innerId);

        if ($innerId === null) {
            return null;
        }

        // The inner service can itself be a decorator, the entity manager is at the end of the chain
        return $this->findInnermostId($container, $container->getDefinition($innerId)) ?? $innerId;
    }

    /** Returns the id of the definition the given service id resolves to, or null when there is none. */
    private function findDefinitionId(ContainerBuilder $container, string $id): string|null
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id) ? $id : null;
    }
}
