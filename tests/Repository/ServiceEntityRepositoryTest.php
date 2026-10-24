<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

use function interface_exists;

class ServiceEntityRepositoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testConstructorThrowsExceptionWhenNoManagerFound(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(<<<'EXCEPTION'
Could not find the entity manager for class "Doctrine\Bundle\DoctrineBundle\Tests\Repository\TestEntity". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.
EXCEPTION);
        /* @phpstan-ignore class.notFound */
        $repo = new ServiceEntityRepository($registry, TestEntity::class);
        $repo->getClassName();
    }
}
