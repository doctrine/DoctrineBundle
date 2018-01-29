<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomServiceRepoEntity;
use Symfony\Bridge\Doctrine\RegistryInterface;

class TestCustomServiceRepoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, TestCustomServiceRepoEntity::class);
    }
}
