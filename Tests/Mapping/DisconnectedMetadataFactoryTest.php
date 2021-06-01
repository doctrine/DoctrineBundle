<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Mapping;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

use function interface_exists;

class DisconnectedMetadataFactoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testCannotFindNamespaceAndPathForMetadata(): void
    {
        $class      = new ClassMetadataInfo(self::class);
        $collection = new ClassMetadataCollection([$class]);

        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $factory  = new DisconnectedMetadataFactory($registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'EXCEPTION'
Can't find base path for "Doctrine\Bundle\DoctrineBundle\Tests\Mapping\DisconnectedMetadataFactoryTest
EXCEPTION
        );
        $factory->findNamespaceAndPathForMetadata($collection);
    }

    public function testFindNamespaceAndPathForMetadata(): void
    {
        /** @psalm-suppress UndefinedClass */
        $class      = new ClassMetadataInfo('\Vendor\Package\Class');
        $collection = new ClassMetadataCollection([$class]);

        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $factory  = new DisconnectedMetadataFactory($registry);

        $factory->findNamespaceAndPathForMetadata($collection, '/path/to/code');

        $this->assertEquals('\Vendor\Package', $collection->getNamespace());
    }
}
