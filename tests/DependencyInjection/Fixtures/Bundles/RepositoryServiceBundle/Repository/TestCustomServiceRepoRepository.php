<?php

declare(strict_types=1);

namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomServiceRepoEntity;

/** @extends ServiceEntityRepository<TestCustomServiceRepoEntity> */
class TestCustomServiceRepoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestCustomServiceRepoEntity::class);
    }
}
