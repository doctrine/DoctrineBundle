<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

use function interface_exists;

class ServiceEntityRepositoryTest extends TestCase
{
    use VerifyDeprecations;

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

    public function testFindTriggersDeprecationWhenPassingNullAsLockMode(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/DoctrineBundle/pull/2284');

        /* @phpstan-ignore class.notFound */
        $repo = new ServiceEntityRepository($this->createRegistry(), TestEntity::class);

        self::assertNull($repo->find(1, null));
    }

    public function testFindDoesNotTriggerDeprecationWhenOmittingLockMode(): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/DoctrineBundle/pull/2284');

        /* @phpstan-ignore class.notFound */
        $repo = new ServiceEntityRepository($this->createRegistry(), TestEntity::class);

        self::assertNull($repo->find(1));
        self::assertNull($repo->find(1, LockMode::NONE));
    }

    private function createRegistry(): ManagerRegistry
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')
            /* @phpstan-ignore class.notFound */
            ->willReturn(new ClassMetadata(TestEntity::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturn($em);

        return $registry;
    }
}
