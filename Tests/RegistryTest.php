<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use ProxyManager\Proxy\LazyLoadingInterface;
use stdClass;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection() : void
    {
        $conn      = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection() : void
    {
        $conn      = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Doctrine ORM Connection named "default" does not exist.
     */
    public function testGetUnknownConnection() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->getConnection('default');
    }

    public function testGetConnectionNames() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager() : void
    {
        $em        = new stdClass();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager() : void
    {
        $em        = new stdClass();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager('default'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Doctrine ORM Manager named "default" does not exist.
     */
    public function testGetUnknownEntityManager() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->getManager('default');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Doctrine ORM Manager named "default" does not exist.
     */
    public function testResetUnknownEntityManager() : void
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->resetManager('default');
    }

    public function testReset() : void
    {
        $noProxyManager = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $noProxyManager->expects($this->once())
            ->method('clear');

        $proxyManager = $this->getMockBuilder(LazyLoadingInterface::class)->getMock();
        $proxyManager->expects($this->once())
            ->method('setProxyInitializer')
            ->with($this->isInstanceOf(Closure::class));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->any())
            ->method('initialized')
            ->withConsecutive(['doctrine.orm.uninitialized_entity_manager'], ['doctrine.orm.noproxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'])
            ->willReturnOnConsecutiveCalls(false, true, true, true);

        $container->expects($this->any())
            ->method('get')
            ->withConsecutive(['doctrine.orm.noproxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'], ['doctrine.orm.proxy_entity_manager'])
            ->willReturnOnConsecutiveCalls($noProxyManager, $proxyManager, $proxyManager);

        $entityManagers = [
            'uninitialized' => 'doctrine.orm.uninitialized_entity_manager',
            'noproxy' => 'doctrine.orm.noproxy_entity_manager',
            'proxy' => 'doctrine.orm.proxy_entity_manager',
        ];

        $registry = new Registry($container, [], $entityManagers, 'default', 'default');
        $registry->reset();
    }
}
