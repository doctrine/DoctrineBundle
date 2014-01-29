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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Filter\DefaultFilterFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter collection that can retrieve filters from the DI container
 *
 * @author Nico Schoenmaker <nschoenmaker@hostnet.nl>
 */
class ContainerFilterFactory extends DefaultFilterFactory
{
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function createFilter(EntityManagerInterface $em, $name)
    {
        $service_id = $em->getConfiguration()->getFilterClassName($name);
        if (strpos($service_id, '.') === false) {
            return parent::createFilter($em, $name);
        }
        return $this->container->get($service_id);
    }
}