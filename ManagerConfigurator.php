<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Bundle\DoctrineBundle\Query\ContainerFilterCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configurator for an EntityManager
 *
 * Gets  a container so it can lazy-load filters
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ManagerConfigurator
{
    private $filters;

    private $container;

    /**
     * Construct.
     * @param array $filters
     * @param ContainerInterface $container
     */
    public function __construct(array $filters, ContainerInterface $container)
    {
        $this->filters = $filters;
        $this->container = $container;
    }

    /**
     * Create a connection by name.
     *
     * @param EntityManager $entityManager
     */
    public function configure(EntityManager $entityManager)
    {
        $configuration = $entityManager->getConfiguration();
        foreach($this->filters as $name => $filter) {
            $configuration->addFilter($name, $filter['identifier']);
        }
        $this->enableFilters($entityManager);
    }

    /**
     * Enable filters for an given entity manager
     *
     * @param EntityManager $entityManager
     *
     * @return null
     */
    private function enableFilters(EntityManager $entityManager)
    {
        $enabledFilters = array_filter($this->filters, function(array $filter) {
            return $filter['enabled'];
        });
        if (empty($enabledFilters)) {
            return;
        }
        $entityManager->setFilters(new ContainerFilterCollection($entityManager, $this->container));

        $filterCollection = $entityManager->getFilters();
        foreach ($enabledFilters as $name => $filter) {
            $filterObject = $filterCollection->enable($name);
            if (null !== $filterObject) {
                foreach($filter['parameters'] as $paramName => $paramValue) {
                    $filterObject->setParameter($paramName, $paramValue);
                }
            }
        }
    }
}
