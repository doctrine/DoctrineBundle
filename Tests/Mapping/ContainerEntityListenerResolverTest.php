<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Mapping;

use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;

use function interface_exists;

class ContainerEntityListenerResolverTest extends TestCase
{
    private ContainerEntityListenerResolver $resolver;

    private Container $container;

    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->resolver  = new ContainerEntityListenerResolver($this->container);
    }

    public function testResolveClass(): void
    {
        $className = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $object    = $this->resolver->resolve($className);

        $this->assertInstanceOf($className, $object);
        $this->assertSame($object, $this->resolver->resolve($className));
    }

    public function testRegisterClassAndResolve(): void
    {
        $className = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $object    = new $className();

        $this->resolver->register($object);

        $this->assertSame($object, $this->resolver->resolve($className));
    }

    public function testRegisterServiceAndResolve(): void
    {
        $className = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $serviceId = 'app.entity_listener';
        $object    = new $className();

        $this->resolver->registerService($className, $serviceId);
        $this->container->set($serviceId, $object);

        $this->assertInstanceOf($className, $this->resolver->resolve($className));
        $this->assertSame($object, $this->resolver->resolve($className));
    }

    public function testRegisterMissingServiceAndResolve(): void
    {
        $className = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $serviceId = 'app.entity_listener';

        $this->resolver->registerService($className, $serviceId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no service named');
        $this->resolver->resolve($className);
    }

    public function testClearOne(): void
    {
        $className1 = EntityListener1::class;
        $className2 = EntityListener2::class;

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

    public function testClearAll(): void
    {
        $className1 = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener1';
        $className2 = '\Doctrine\Bundle\DoctrineBundle\Tests\Mapping\EntityListener2';

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

    public function testRegisterStringException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An object was expected, but got "string".');
        $this->resolver->register('CompanyContractListener');
    }
}

class EntityListener1
{
}

class EntityListener2
{
}
