<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @template T of object
 * @extends EntityRepository<T>
 */
class TestCustomClassRepoRepository extends EntityRepository
{
    public function getEntityManager(): EntityManagerInterface
    {
        return parent::getEntityManager();
    }
}
