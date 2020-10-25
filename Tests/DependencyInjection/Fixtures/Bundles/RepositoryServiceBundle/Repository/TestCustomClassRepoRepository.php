<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

class TestCustomClassRepoRepository extends EntityRepository
{
    public function getEntityManager(): EntityManager
    {
        return parent::getEntityManager();
    }
}
