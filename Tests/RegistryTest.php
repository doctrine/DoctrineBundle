<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection()
    {
        $conn = $this->createMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection()
    {
        $conn = $this->createMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    public function testGetUnknownConnection()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Connection named "default" does not exist.');
        $registry->getConnection('default');
    }

    public function testGetConnectionNames()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertEquals(array('default' => 'doctrine.dbal.default_connection'), $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager()
    {
        $em = new \stdClass();
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager()
    {
        $em = new \stdClass();
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');

        $this->assertSame($em, $registry->getManager('default'));
    }

    public function testGetUnknownEntityManager()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Manager named "default" does not exist.');
        $registry->getManager('default');
    }

    public function testResetUnknownEntityManager()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Manager named "default" does not exist.');
        $registry->resetManager('default');
    }
}
