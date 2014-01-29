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

namespace Doctrine\Bundle\DoctrineBundle\Query;

use Doctrine\ORM\Query\FilterCollection;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter collection that can retrieve filters from the DI container
 *
 * @author Nico Schoenmaker <nschoenmaker@hostnet.nl>
 */
class ContainerFilterCollection extends FilterCollection
{
    private $config;
    private $container;

    /**
     * @param EntityManagerInterface $em
     * @param ContainerInterface $container
     */
    public function __construct(EntityManagerInterface $em, ContainerInterface $container)
    {
        parent::__construct($em);
        $this->config = $em->getConfiguration();
        $this->container = $container;
    }

    /**
     * @see \Doctrine\ORM\Query\FilterCollection::createFilterClass()
     */
    protected function createFilterClass($name)
    {
        $service_id = $this->config->getFilterClassName($name);
        if (strpos($service_id, '.') === false) {
            return parent::createFilterClass($name);
        }
        return $this->container->get($service_id);
    }
}