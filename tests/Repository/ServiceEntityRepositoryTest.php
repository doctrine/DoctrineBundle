<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\LazyGhostTrait;
use Symfony\Component\VarExporter\LazyObjectInterface;

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
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(<<<'EXCEPTION'
Could not find the entity manager for class "Doctrine\Bundle\DoctrineBundle\Tests\Repository\TestEntity". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.
EXCEPTION);
        /** @psalm-suppress UndefinedClass */
        $repo = new ServiceEntityRepository($registry, TestEntity::class);
        $repo->getClassName();
    }

    /** @requires function \Symfony\Component\VarExporter\ProxyHelper::generateLazyGhost */
    public function testConstructInitializesWhenImplementingLazyObjectInterface(): void
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $this->expectException(LogicException::class);

        /** @psalm-suppress UndefinedClass */
        new class ($registry, TestEntity::class) extends ServiceEntityRepository implements LazyObjectInterface {
            use LazyGhostTrait;
        };
    }
}
