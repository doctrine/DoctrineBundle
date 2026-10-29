<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures\Article;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
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
use function method_exists;
use function property_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

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

    public function testFindDoesNotRefreshAManagedEntity(): void
    {
        if (property_exists(EntityRepository::class, '_entityName')) {
            self::markTestSkipped('ServiceEntityRepository does not override find() with ORM 2');
        }

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Fixtures']));

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir());
            $config->setProxyNamespace('Proxies');
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Article::class)]);

        $article = new Article(1, 'Original title');
        $em->persist($article);
        $em->flush();

        $article->title = 'Changed title';

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repository = new ServiceEntityRepository($registry, Article::class);

        self::assertSame($article, $repository->find(1));
        self::assertSame('Changed title', $article->title);
    }
}
