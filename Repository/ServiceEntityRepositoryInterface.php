<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * This interface signals that your repository should be loaded from the container.
 */
interface ServiceEntityRepositoryInterface
{
    /**
     * @param EntityManagerInterface $em
     *
     * @return mixed
     */
    public function setEntityManager(EntityManagerInterface $em);
}
