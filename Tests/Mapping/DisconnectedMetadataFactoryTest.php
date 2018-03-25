<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Mapping;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class DisconnectedMetadataFactoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (class_exists('Doctrine\\ORM\\Version')) {
            return;
        }

        $this->markTestSkipped('Doctrine ORM is not available.');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can't find base path for "Doctrine\Bundle\DoctrineBundle\Tests\Mapping\DisconnectedMetadataFactoryTest
     */
    public function testCannotFindNamespaceAndPathForMetadata()
    {
        $class      = new ClassMetadataInfo(__CLASS__);
        $collection = new ClassMetadataCollection([$class]);

        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $factory  = new DisconnectedMetadataFactory($registry);

        $factory->findNamespaceAndPathForMetadata($collection);
    }

    public function testFindNamespaceAndPathForMetadata()
    {
        $class      = new ClassMetadataInfo('\Vendor\Package\Class');
        $collection = new ClassMetadataCollection([$class]);

        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $factory  = new DisconnectedMetadataFactory($registry);

        $factory->findNamespaceAndPathForMetadata($collection, '/path/to/code');

        $this->assertEquals('\Vendor\Package', $collection->getNamespace());
    }
}
