<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ServiceEntityRepositoryTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

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
