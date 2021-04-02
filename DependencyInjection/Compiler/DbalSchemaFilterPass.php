<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

/**
 * Processes the doctrine.dbal.schema_filter
 */
class DbalSchemaFilterPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $filters = $container->findTaggedServiceIds('doctrine.dbal.schema_filter');

        $connectionFilters = [];
        foreach ($filters as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $name = $attributes['connection'] ?? $container->getParameter('doctrine.default_connection');

                if (! isset($connectionFilters[$name])) {
                    $connectionFilters[$name] = [];
                }

                $connectionFilters[$name][] = new Reference($id);
            }
        }

        foreach ($connectionFilters as $name => $references) {
            $configurationId = sprintf('doctrine.dbal.%s_connection.configuration', $name);

            if (! $container->hasDefinition($configurationId)) {
                continue;
            }

            $definition = new ChildDefinition('doctrine.dbal.schema_asset_filter_manager');
            $definition->setArgument(0, $references);

            $id = sprintf('doctrine.dbal.%s_schema_asset_filter_manager', $name);
            $container->setDefinition($id, $definition);
            $container->findDefinition($configurationId)
                ->addMethodCall('setSchemaAssetsFilter', [new Reference($id)]);
        }
    }
}
