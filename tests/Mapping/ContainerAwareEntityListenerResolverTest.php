<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Mapping;

use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerAwareEntityListenerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareEntityListenerResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerAwareEntityListenerResolver
     */
    private $resolver;

    /**
     * @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    protected function setUp()
    {
        if (!interface_exists('\Doctrine\ORM\Mapping\EntityListenerResolver')) {
            $this->markTestSkipped('Entity listeners are not supported in this Doctrine version');
        }

        parent::setUp();

        $this->container = $this->getMockForAbstractClass('\Symfony\Component\DependencyInjection\ContainerInterface');
        $this->resolver  = new ContainerAwareEntityListenerResolver($this->container);
    }

    public function testResolveClass()
    {
        $className  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $object     = $this->resolver->resolve($className);

        $this->assertInstanceOf($className, $object);
        $this->assertSame($object, $this->resolver->resolve($className));
    }

    public function testRegisterClassAndResolve()
    {
        $className  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $object     = new $className();

        $this->resolver->register($object);

        $this->assertSame($object, $this->resolver->resolve($className));
    }

    public function testRegisterServiceAndResolve()
    {
        $className  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $serviceId  = 'app.entity_listener';
        $object     = new $className();

        $this->resolver->registerService($className, $serviceId);
        $this->container
            ->expects($this->any())
            ->method('has')
            ->with($serviceId)
            ->will($this->returnValue(true))
        ;
        $this->container
            ->expects($this->any())
            ->method('get')
            ->with($serviceId)
            ->will($this->returnValue($object))
        ;

        $this->assertInstanceOf($className, $this->resolver->resolve($className));
        $this->assertSame($object, $this->resolver->resolve($className));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage There is no service named
     */
    public function testRegisterMissingServiceAndResolve()
    {
        $className  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $serviceId  = 'app.entity_listener';

        $this->resolver->registerService($className, $serviceId);
        $this->container
            ->expects($this->any())
            ->method('has')
            ->with($serviceId)
            ->will($this->returnValue(false))
        ;

        $this->resolver->resolve($className);
    }

    public function testClearOne()
    {
        $className1  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $className2  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener2';

        $obj1 = $this->resolver->resolve($className1);
        $obj2 = $this->resolver->resolve($className2);

        $this->assertInstanceOf($className1, $obj1);
        $this->assertInstanceOf($className2, $obj2);

        $this->assertSame($obj1, $this->resolver->resolve($className1));
        $this->assertSame($obj2, $this->resolver->resolve($className2));

        $this->resolver->clear($className1);

        $this->assertInstanceOf($className1, $this->resolver->resolve($className1));
        $this->assertInstanceOf($className2, $this->resolver->resolve($className2));

        $this->assertNotSame($obj1, $this->resolver->resolve($className1));
        $this->assertSame($obj2, $this->resolver->resolve($className2));
    }

    public function testClearAll()
    {
        $className1  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $className2  = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener2';

        $obj1 = $this->resolver->resolve($className1);
        $obj2 = $this->resolver->resolve($className2);

        $this->assertInstanceOf($className1, $obj1);
        $this->assertInstanceOf($className2, $obj2);

        $this->assertSame($obj1, $this->resolver->resolve($className1));
        $this->assertSame($obj2, $this->resolver->resolve($className2));

        $this->resolver->clear();

        $this->assertInstanceOf($className1, $this->resolver->resolve($className1));
        $this->assertInstanceOf($className2, $this->resolver->resolve($className2));

        $this->assertNotSame($obj1, $this->resolver->resolve($className1));
        $this->assertNotSame($obj2, $this->resolver->resolve($className2));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage An object was expected, but got "string".
     */
    public function testRegisterStringException()
    {
        $this->resolver->register('CompanyContractListener');
    }
}

class EntityListener1
{
}

class EntityListener2
{
}
