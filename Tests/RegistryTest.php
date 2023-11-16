<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use InvalidArgumentException;
use ProxyManager\Proxy\ProxyInterface;
use stdClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function assert;
use function class_exists;
use function interface_exists;

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
        $conn      = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $container = new Container();
        $container->set('doctrine.dbal.default_connection', $conn);

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection(): void
    {
        $conn      = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
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
        $em        = new stdClass();
        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $em);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager(): void
    {
        $em        = new stdClass();
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
        if (PHP_VERSION_ID < 80000 && ! class_exists(AnnotationReader::class)) {
            self::markTestSkipped('This test requires Annotations when run on PHP 7');
        }

        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new TestKernel();
        $kernel->boot();

        $container     = $kernel->getContainer();
        $registry      = $container->get('doctrine');
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        $repository    = $entityManager->getRepository(TestCustomClassRepoEntity::class);

        $this->assertInstanceOf(interface_exists(LazyObjectInterface::class) ? LazyObjectInterface::class : ProxyInterface::class, $entityManager);
        assert($entityManager instanceof EntityManagerInterface);
        assert($registry instanceof Registry);
        assert($repository instanceof TestCustomClassRepoRepository);

        $entity = new TestCustomClassRepoEntity();
        $repository->getEntityManager()->persist($entity);

        $this->assertTrue($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertTrue($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $registry->reset();

        $this->assertFalse($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertFalse($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $entityManager->flush();
    }
}
