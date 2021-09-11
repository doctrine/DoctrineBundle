<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use InvalidArgumentException;
use ProxyManager\Proxy\ProxyInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function assert;
use function interface_exists;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection(): void
    {
        $conn      = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection(): void
    {
        $conn      = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    public function testGetUnknownConnection(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Connection named "default" does not exist.');
        $registry->getConnection('default');
    }

    public function testGetConnectionNames(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager(): void
    {
        $em        = new stdClass();
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager(): void
    {
        $em        = new stdClass();
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager('default'));
    }

    public function testGetUnknownEntityManager(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.'
        );
        $registry->getManager('default');
    }

    public function testResetUnknownEntityManager(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.'
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

        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->any())
            ->method('initialized')
            ->withConsecutive(['doctrine.orm.uninitialized_entity_manager'], ['doctrine.orm.noproxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'])
            ->willReturnOnConsecutiveCalls(false, true, true, true);

        $container->expects($this->any())
            ->method('get')
            ->withConsecutive(['doctrine.orm.noproxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'])
            ->willReturnOnConsecutiveCalls($noProxyManager, $proxyManager, $proxyManager, $proxyManager);

        $entityManagers = [
            'uninitialized' => 'doctrine.orm.uninitialized_entity_manager',
            'noproxy' => 'doctrine.orm.noproxy_entity_manager',
            'proxy' => 'doctrine.orm.proxy_entity_manager',
        ];

        $registry = new Registry($container, [], $entityManagers, 'default', 'default');
        $registry->reset();
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
        $repository    = $entityManager->getRepository(TestCustomClassRepoEntity::class);

        $this->assertInstanceOf(ProxyInterface::class, $entityManager);
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
