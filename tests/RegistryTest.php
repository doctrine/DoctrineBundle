<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestEntityManagerDecorator;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhp;
use ProxyManager\Proxy\ProxyInterface;
use ReflectionClass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function assert;
use function interface_exists;
use function restore_exception_handler;

use const PHP_VERSION_ID;

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

    #[IgnoreDeprecations]
    #[RequiresPhp('<8.4.0')]
    public function testReset(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $noProxyManager = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $noProxyManager->expects($this->once())
            ->method('clear');

        $proxyManager = $this->createMock(LazyLoadingEntityManagerInterface::class);
        $proxyManager->expects($this->once())
            ->method('setProxyInitializer')
            ->with($this->isInstanceOf(Closure::class));

        $container = new Container();
        $container->set('doctrine.orm.noproxy_entity_manager', $noProxyManager);
        $container->set('doctrine.orm.proxy_entity_manager', $proxyManager);

        $entityManagers = [
            'uninitialized' => 'doctrine.orm.uninitialized_entity_manager',
            'noproxy' => 'doctrine.orm.noproxy_entity_manager',
            'proxy' => 'doctrine.orm.proxy_entity_manager',
        ];

        $registry = new Registry($container, [], $entityManagers, 'default', 'default');
        $registry->reset();
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testResetLazyObject(): void
    {
        if (! interface_exists(EntityManagerInterface::class) || ! interface_exists(LazyObjectInterface::class)) {
            self::markTestSkipped('This test requires ORM and VarExporter 6.2+');
        }

        $ghostManager = $this->createMock(LazyObjectEntityManagerInterface::class);
        $ghostManager->expects($this->once())->method('resetLazyObject')->willReturn(true);

        $container = new Container();
        $container->set('doctrine.orm.ghost_entity_manager', $ghostManager);

        $entityManagers = [
            'uninitialized' => 'doctrine.orm.uninitialized_entity_manager',
            'ghost' => 'doctrine.orm.ghost_entity_manager',
        ];

        (new Registry($container, [], $entityManagers, 'default', 'default'))->reset();
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

        if (PHP_VERSION_ID < 80400) {
            $this->assertInstanceOf(interface_exists(LazyObjectInterface::class) ? LazyObjectInterface::class : ProxyInterface::class, $entityManager);
        }

        assert($entityManager instanceof EntityManagerInterface);
        assert($registry instanceof Registry);

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

    /** @param Closure(Registry): ObjectManager $reset */
    #[DataProvider('resetProvider')]
    public function testResetClosedDecoratedEntityManager(Closure $reset): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = $this->createKernelWithDecoratedEntityManager();
        $kernel->boot();

        $registry = $kernel->getContainer()->get('doctrine');
        assert($registry instanceof Registry);

        $entityManager = $registry->getManager();
        $this->assertInstanceOf(TestEntityManagerDecorator::class, $entityManager);

        $entityManager->close();

        $this->assertSame($entityManager, $reset($registry));
        $this->assertTrue($entityManager->isOpen());

        restore_exception_handler();
    }

    /** @return array<string, array{Closure(Registry): ObjectManager}> */
    public static function resetProvider(): array
    {
        return [
            'resetManager()' => [static fn (Registry $registry): ObjectManager => $registry->resetManager()],
            'kernel.reset' => [
                static function (Registry $registry): ObjectManager {
                    $registry->reset();

                    return $registry->getManager();
                },
            ],
        ];
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testResetClearsDecoratedEntityManagerWhoseInnerManagerIsUnused(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        // A decorator can work on another entity manager than the one it decorates, which then stays uninitialized
        $decorator = $this->createMock(EntityManagerInterface::class);
        $decorator->method('isOpen')->willReturn(true);
        $decorator->expects($this->once())->method('clear');

        $innerManager = (new ReflectionClass(EntityManager::class))->newLazyGhost(static function (): void {
        });

        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $decorator);
        $container->set('decorator.inner', $innerManager);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default', ['doctrine.orm.default_entity_manager' => 'decorator.inner']);
        $registry->reset();
    }

    private function createKernelWithDecoratedEntityManager(): TestKernel
    {
        return new class () extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);

                $loader->load(static function (ContainerBuilder $container): void {
                    $container->register(TestEntityManagerDecorator::class, TestEntityManagerDecorator::class)
                        ->setDecoratedService('doctrine.orm.default_entity_manager')
                        ->setArguments([new Reference('.inner')]);
                });
            }
        };
    }
}
