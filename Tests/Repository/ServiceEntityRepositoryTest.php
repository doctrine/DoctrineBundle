<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ServiceEntityRepositoryTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Could not find the entity manager for class "Doctrine\Bundle\DoctrineBundle\Tests\Repository\TestEntity". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.
     */
    public function testConstructorThrowsExceptionWhenNoManagerFound()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        new ServiceEntityRepository($registry, TestEntity::class);
    }
}
