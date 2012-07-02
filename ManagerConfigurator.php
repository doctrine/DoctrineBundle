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

/**
 * Configurator for an EntityManager
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ManagerConfigurator
{
    private $enabledFilters = array();

    /**
     * Construct.
     *
     * @param array $enabledFilters
     */
    public function __construct(array $enabledFilters)
    {
        $this->enabledFilters = $enabledFilters;
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
            $filterCollection->enable($filter);
        }
    }
}
