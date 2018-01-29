<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection()
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

    public function testGetConnection()
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
    public function testGetUnknownConnection()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->getConnection('default');
    }

    public function testGetConnectionNames()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager()
    {
        $em        = new \stdClass();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager()
    {
        $em        = new \stdClass();
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
    public function testGetUnknownEntityManager()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->getManager('default');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Doctrine ORM Manager named "default" does not exist.
     */
    public function testResetUnknownEntityManager()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $registry  = new Registry($container, [], [], 'default', 'default');

        $registry->resetManager('default');
    }
}
