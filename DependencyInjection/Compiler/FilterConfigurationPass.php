<?php

/*
 * This file is part of the Doctrine Bundle (c) Fabien Potencier
 * <fabien@symfony.com> (c) Doctrine Project, Benjamin Eberlei
 * <kontakt@beberlei.de> For the full copyright and license information, please
 * view the LICENSE file that was distributed with this source code.
 */
namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Combines the filters from the config with the filters that are tagged with
 * doctrine.orm.filter
 *
 * @author Nico Schoenmaker<nschoenmaker@hostnet.nl>
 */
class FilterConfigurationPass implements CompilerPassInterface
{

    private $tagPrefix;

    public function __construct($tagPrefix)
    {
        $this->tagPrefix = $tagPrefix;
    }

    public function process(ContainerBuilder $container)
    {
        $filtersFromTags = $this->gatherFiltersFromTags(
                $container->findTaggedServiceIds($this->tagPrefix . '.orm.filter'));
        $entityManagers = $container->getParameter('doctrine.entity_managers');
        foreach ($entityManagers as $name => $service_id) {
            // Get the configurator of the entity manager, this needs to be a
            // Reference
            $callable = $container->getDefinition($service_id)->getConfigurator();
            if (! is_array($callable) || count($callable) != 2 ||
                     ! isset($callable[0])) {
                throw new \RuntimeException(
                        sprintf(
                                'Should have gotten a callable as configurator of "%s"',
                                $service_id));
            }
            $reference = $callable[0];
            if (! $reference instanceof Reference) {
                throw new \RuntimeException(
                        'Expected to get an instance of reference');
            }
            $definition = $container->getDefinition($reference);
            $this->importTaggedFilters($definition, $filtersFromTags);
        }
    }

    private function gatherFiltersFromTags(array $taggedFilters)
    {
        $filtersFromTags = array();
        foreach ($taggedFilters as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                if (! isset($attributes['filter_name'])) {
                    throw new \RuntimeException(
                            sprintf(
                                    'Service %s is missing a name attribute on it\'s tag',
                                    $id));
                }
                $enabled = isset($attributes['enabled']) ? $attributes['enabled'] : true;

                // TODO enable parameter support
                $filtersFromTags[$attributes['filter_name']] = array(
                        'identifier' => $id,
                        'enabled' => $enabled,
                        'parameters' => array()
                );
            }
        }
        return $filtersFromTags;
    }

    private function importTaggedFilters(Definition $managerConfigurator,
            $filtersFromTags)
    {
        $filters = $managerConfigurator->getArgument(0);
        $resultingFilters = array();

        foreach ($filtersFromTags as $name => $filter) {
            $resultingFilters[$name] = $filter;
            if (isset($filters[$name]['enabled'])) {
                $resultingFilters[$name]['enabled'] = $filters[$name]['enabled'];
            }
        }

        $filters = array_diff_key($filters, $filtersFromTags);
        foreach ($filters as $name => $filter) {
            if (! isset($filter['class'])) {
                throw new InvalidConfigurationException(
                        sprintf('Filter %s should have a class attribute',
                                $name));
            }
            $resultingFilters[$name] = array(
                    'identifier' => $filter['class'],
                    'enabled' => $filter['enabled'],
                    'parameters' => $filter['parameters']
            );
        }
        $managerConfigurator->replaceArgument(0, $resultingFilters);
    }
}
