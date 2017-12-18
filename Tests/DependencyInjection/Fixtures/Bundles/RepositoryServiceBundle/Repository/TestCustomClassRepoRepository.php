<?php


namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomServiceRepoEntity;
use Symfony\Bridge\Doctrine\RegistryInterface;

class TestCustomClassRepoRepository extends EntityRepository
{
}
