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

                $resolver = sprintf('doctrine.orm.%s_entity_listener_resolver', $name);
                if ($container->hasAlias($resolver)) {
                    $resolver = (string) $container->getAlias($resolver);
                }

                if (!$container->hasDefinition($resolver)) {
                    continue;
                }

                if (isset($attributes['entity']) && isset($attributes['event'])) {
                    $this->attachToListener($container, $name, $id, $attributes);
                }

                $container->getDefinition($resolver)->addMethodCall('register', array(new Reference($id)));
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
