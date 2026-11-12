<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\CustomClassMetadataFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestDefaultRepoEntityProxy;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Fixtures\Bundles\AttributesBundle\Entity\TestClass;
use Fixtures\Bundles\AttributesBundle\Entity\TestCustomIdGeneratorEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function assert;
use function interface_exists;
use function restore_exception_handler;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection(): void
    {
        $conn      = $this->createStub(Connection::class);
        $container = new Container();
        $container->set('doctrine.dbal.default_connection', $conn);

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection(): void
    {
        $conn      = $this->createStub(Connection::class);
        $container = new Container();
        $container->set('doctrine.dbal.default_connection', $conn);

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    public function testGetUnknownConnection(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Connection named "default" does not exist.');
        $registry->getConnection('default');
    }

    public function testGetConnectionNames(): void
    {
        $registry = new Registry(new Container(), ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager(): void
    {
        $em        = $this->createStub(ObjectManager::class);
        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $em);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager(): void
    {
        $em        = $this->createStub(ObjectManager::class);
        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $em);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager('default'));
    }

    public function testGetUnknownEntityManager(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.',
        );
        $registry->getManager('default');
    }

    public function testResetUnknownEntityManager(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.',
        );
        $registry->resetManager('default');
    }

    public function testIdentityMapsStayConsistentAfterReset(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new TestKernel();
        $kernel->boot();

        $container     = $kernel->getContainer();
        $registry      = $container->get('doctrine');
        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $repository = $entityManager->getRepository(TestCustomClassRepoEntity::class);
        assert($repository instanceof TestCustomClassRepoRepository);

        $entity = new TestCustomClassRepoEntity();
        $repository->getEntityManager()->persist($entity);

        $this->assertTrue($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertTrue($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $registry->reset();

        $this->assertFalse($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertFalse($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $entityManager->flush();

        restore_exception_handler();
    }

    public function testGetManagerForClassDoesNotInitializeManagersThatDoNotMapTheClass(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = self::bootKernel([]);
        $registry  = $container->get('doctrine');
        $em        = $container->get('doctrine.orm.default_entity_manager');

        $this->assertNull($registry->getManagerForClass(TestCustomClassRepoRepository::class));
        $this->assertTrue(self::isUninitialized($em));

        $this->assertSame($em, $registry->getManagerForClass(TestCustomClassRepoEntity::class));
        $this->assertSame($em, $registry->getManagerForClass(TestDefaultRepoEntityProxy::class));

        restore_exception_handler();
    }

    public function testGetManagerForClassWithSeveralManagers(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = self::bootKernel([
            'entity_managers' => [
                'other' => [
                    'mappings' => [
                        'AttributesBundle' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/DependencyInjection/Fixtures/Bundles/AttributesBundle/Entity',
                            'prefix' => 'Fixtures\Bundles\AttributesBundle\Entity',
                        ],
                        'RepositoryServiceBundle' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/Entity',
                            'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Entity',
                        ],
                    ],
                ],
            ],
        ]);

        $registry = $container->get('doctrine');
        $default  = $container->get('doctrine.orm.default_entity_manager');
        $other    = $container->get('doctrine.orm.other_entity_manager');

        $this->assertSame($other, $registry->getManagerForClass(TestCustomIdGeneratorEntity::class));
        $this->assertSame($default, $registry->getManagerForClass(TestDefaultRepoEntity::class));
        $this->assertNull($registry->getManagerForClass(TestClass::class));
        $this->assertTrue(self::isUninitialized($default));
        $this->assertTrue(self::isUninitialized($other));

        $registry->getManager('other')->getMetadataFactory();

        $this->assertSame($other, $registry->getManagerForClass(TestCustomIdGeneratorEntity::class));
        $this->assertSame($default, $registry->getManagerForClass(TestDefaultRepoEntity::class));
        $this->assertNull($registry->getManagerForClass(TestClass::class));
        $this->assertTrue(self::isUninitialized($default));

        restore_exception_handler();
    }

    public function testGetManagerForClassUsesCustomMetadataFactories(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = self::bootKernel(['class_metadata_factory_name' => CustomClassMetadataFactory::class]);

        $this->assertSame($container->get('doctrine.orm.default_entity_manager'), $container->get('doctrine')->getManagerForClass(stdClass::class));

        restore_exception_handler();
    }

    /** @param array<string, mixed> $orm */
    private static function bootKernel(array $orm): ContainerInterface
    {
        $kernel = new class ($orm) extends TestKernel {
            /** @param array<string, mixed> $orm */
            public function __construct(private array $orm)
            {
                parent::__construct();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);

                $loader->load(function (ContainerBuilder $container): void {
                    $container->loadFromExtension('doctrine', ['orm' => $this->orm]);
                });
            }
        };
        $kernel->boot();

        return $kernel->getContainer();
    }

    private static function isUninitialized(object $manager): bool
    {
        if ($manager instanceof LazyObjectInterface) {
            return ! $manager->isLazyObjectInitialized();
        }

        return (new ReflectionClass($manager))->isUninitializedLazyObject($manager);
    }
}
