<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\LazyGhostTrait;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Component\VarExporter\ProxyHelper;

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

    #[IgnoreDeprecations]
    #[RequiresMethod(ProxyHelper::class, 'generateLazyGhost')]
    #[RequiresPhp('>= 8.4.0')]
    public function testConstructInitializesWhenImplementingLazyObjectInterface(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $this->expectException(LogicException::class);

        /* @phpstan-ignore class.notFound, expr.resultUnused */
        new class ($registry, TestEntity::class) extends ServiceEntityRepository implements LazyObjectInterface {
            use LazyGhostTrait;
        };
    }
}
