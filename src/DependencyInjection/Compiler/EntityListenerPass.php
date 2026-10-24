<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver;
use Doctrine\Bundle\DoctrineBundle\Mapping\EntityListenerServiceResolver;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function is_a;
use function method_exists;
use function sprintf;
use function substr;
use function usort;

/**
 * Class for Symfony bundles to register entity listeners
 *
 * @internal
 */
final class EntityListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $lazyServiceReferencesByResolver = [];

        $serviceTags = [];
        foreach ($container->findTaggedServiceIds('doctrine.orm.entity_listener', true) as $id => $tags) {
            foreach ($tags as $attributes) {
                $serviceTags[] = [
                    'serviceId' => $id,
                    'attributes' => $attributes,
                ];
            }
        }

        usort($serviceTags, static fn (array $a, array $b) => ($b['attributes']['priority'] ?? 0) <=> ($a['attributes']['priority'] ?? 0));

        foreach ($serviceTags as $tag) {
            $id            = $tag['serviceId'];
            $attributes    = $tag['attributes'];
            $name          = $attributes['entity_manager'] ?? $container->getParameter('doctrine.default_entity_manager');
            $entityManager = sprintf('doctrine.orm.%s_entity_manager', $name);

            if (! $container->hasDefinition($entityManager)) {
                continue;
            }

            $resolverId = sprintf('doctrine.orm.%s_entity_listener_resolver', $name);

            if (! $container->has($resolverId)) {
                continue;
            }

            $resolver = $container->findDefinition($resolverId);
            $resolver->setPublic(true);

            if (isset($attributes['entity'])) {
                $this->attachToListener($container, $name, $this->getConcreteDefinitionClass($container->findDefinition($id), $container, $id), $attributes);
            }

            $resolverClass                 = $this->getResolverClass($resolver, $container, $resolverId);
            $resolverSupportsLazyListeners = is_a($resolverClass, EntityListenerServiceResolver::class, true);

            $lazyByAttribute = isset($attributes['lazy']) && $attributes['lazy'];
            if ($lazyByAttribute && ! $resolverSupportsLazyListeners) {
                throw new InvalidArgumentException(sprintf(
                    'Lazy-loaded entity listeners can only be resolved by a resolver implementing %s.',
                    EntityListenerServiceResolver::class,
                ));
            }

            if (! isset($attributes['lazy']) && $resolverSupportsLazyListeners || $lazyByAttribute) {
                $listener = $container->findDefinition($id);

                $resolver->addMethodCall('registerService', [$this->getConcreteDefinitionClass($listener, $container, $id), $id]);

                // if the resolver uses the default class we will use a service locator for all listeners
                if ($resolverClass === ContainerEntityListenerResolver::class) {
                    if (! isset($lazyServiceReferencesByResolver[$resolverId])) {
                        $lazyServiceReferencesByResolver[$resolverId] = [];
                    }

                    $lazyServiceReferencesByResolver[$resolverId][$id] = new Reference($id);
                } else {
                    $listener->setPublic(true);
                }
            } else {
                $resolver->addMethodCall('register', [new Reference($id)]);
            }
        }

        foreach ($lazyServiceReferencesByResolver as $resolverId => $listenerReferences) {
            $container->findDefinition($resolverId)->setArgument(0, ServiceLocatorTagPass::register($container, $listenerReferences));
        }
    }

    /** @param array{entity: class-string, event?: ?string, method?: string} $attributes */
    private function attachToListener(ContainerBuilder $container, string $name, string $class, array $attributes): void
    {
        $listenerId = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $name);

        if (! $container->has($listenerId)) {
            return;
        }

        $args = [
            $attributes['entity'],
            $class,
            $attributes['event'] ?? null,
        ];

        if (isset($attributes['method'])) {
            $args[] = $attributes['method'];
        } elseif (isset($attributes['event']) && ! method_exists($class, $attributes['event']) && method_exists($class, '__invoke')) {
            $args[] = '__invoke';
        }

        $container->findDefinition($listenerId)->addMethodCall('addEntityListener', $args);
    }

    private function getResolverClass(Definition $resolver, ContainerBuilder $container, string $id): string
    {
        $resolverClass = $this->getConcreteDefinitionClass($resolver, $container, $id);

        if (substr($resolverClass, 0, 1) === '%') {
            // resolve container parameter first
            $resolverClass = $container->getParameterBag()->resolveValue($resolverClass);
        }

        return $resolverClass;
    }

    private function getConcreteDefinitionClass(Definition $definition, ContainerBuilder $container, string $id): string
    {
        $class = $definition->getClass();
        if ($class) {
            return $class;
        }

        while ($definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());

            $class = $definition->getClass();
            if ($class) {
                return $class;
            }
        }

        throw new InvalidArgumentException(sprintf('The service "%s" must define its class.', $id));
    }
}
