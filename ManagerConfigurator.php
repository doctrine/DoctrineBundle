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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Configurator for an EntityManager
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ManagerConfigurator
{
    private $enabledFilters = array();
    private $filtersParameters = array();

    /**
     * Construct.
     *
     * @param array $enabledFilters
     */
    public function __construct(array $enabledFilters, array $filtersParameters)
    {
        $this->enabledFilters = $enabledFilters;
        $this->filtersParameters = $filtersParameters;
    }

    /**
     * Create a connection by name.
     *
     * @param EntityManager $entityManager
     */
    public function configure(EntityManager $entityManager)
    {
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
        if (empty($this->enabledFilters)) {
            return;
        }

        $filterCollection = $entityManager->getFilters();
        foreach ($this->enabledFilters as $filter) {
            $filterObject = $filterCollection->enable($filter);
            if (null !== $filterObject) {
                $this->setFilterParameters($filter, $filterObject);
            }
        }
    }

    /**
     * Set defaults parameters for a given filter
     *
     * @param string $name Filter name
     * @param SQLFilter $filter Filter object
     *
     * @return null
     */
    private function setFilterParameters($name, SQLFilter $filter)
    {
        if (!empty($this->filtersParameters[$name])) {
            $parameters = $this->filtersParameters[$name];
            foreach ($parameters as $paramName => $paramValue) {
                $filter->setParameter($paramName, $paramValue);
            }
        }
    }
}
