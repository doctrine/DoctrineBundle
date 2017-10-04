<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class for Symfony bundles to register entity listeners
 *
 * @author Sander Marechal <s.marechal@jejik.com>
 */
class EntityListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $resolvers = $container->findTaggedServiceIds('doctrine.orm.entity_listener');

        foreach ($resolvers as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $name = isset($attributes['entity_manager']) ? $attributes['entity_manager'] : $container->getParameter('doctrine.default_entity_manager');
                $entityManager = sprintf('doctrine.orm.%s_entity_manager', $name);

                if (!$container->hasDefinition($entityManager)) {
                    continue;
                }

                $resolverId = sprintf('doctrine.orm.%s_entity_listener_resolver', $name);

                if (!$container->has($resolverId)) {
                    continue;
                }

                $resolver = $container->findDefinition($resolverId);

                if (isset($attributes['entity']) && isset($attributes['event'])) {
                    $this->attachToListener($container, $name, $id, $attributes);
                }

                if (isset($attributes['lazy']) && $attributes['lazy']) {
                    $listener = $container->findDefinition($id);

                    if ($listener->isAbstract()) {
                        throw new InvalidArgumentException(sprintf('The service "%s" must not be abstract as this entity listener is lazy-loaded.', $id));
                    }

                    $interface = 'Doctrine\\Bundle\\DoctrineBundle\\Mapping\\EntityListenerServiceResolver';
                    $class = $resolver->getClass();

                    if (substr($class, 0, 1) === '%') {
                        // resolve container parameter first
                        $class = $container->getParameterBag()->resolveValue($resolver->getClass());
                    }

                    if (!is_a($class, $interface, true)) {
                        throw new InvalidArgumentException(
                            sprintf('Lazy-loaded entity listeners can only be resolved by a resolver implementing %s.', $interface)
                        );
                    }

                    $listener->setPublic(true);

                    $resolver->addMethodCall('registerService', array($listener->getClass(), $id));
                } else {
                    $resolver->addMethodCall('register', array(new Reference($id)));
                }
            }
        }
    }

    private function attachToListener(ContainerBuilder $container, $name, $id, array $attributes)
    {
        $listenerId = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $name);

        if (!$container->has($listenerId)) {
            return;
        }

        $serviceDef = $container->getDefinition($id);

        $args = array(
            $attributes['entity'],
            $serviceDef->getClass(),
            $attributes['event'],
        );

        if (isset($attributes['method'])) {
            $args[] = $attributes['method'];
        }

        $container->findDefinition($listenerId)->addMethodCall('addEntityListener', $args);
    }
}
