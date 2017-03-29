<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        if (!class_exists('Doctrine\\ORM\\Version')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }
    }

    public function testCannotFindNamespaceAndPathForMetadata()
    {
        $class = new ClassMetadataInfo(__CLASS__);
        $collection = new ClassMetadataCollection(array($class));

        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $factory = new DisconnectedMetadataFactory($registry);

        $this->setExpectedException('RuntimeException', 'Can\'t find base path for "Doctrine\Bundle\DoctrineBundle\Tests\Mapping\DisconnectedMetadataFactoryTest');
        $factory->findNamespaceAndPathForMetadata($collection);
    }

    public function testFindNamespaceAndPathForMetadata()
    {
        $class = new ClassMetadataInfo('\Vendor\Package\Class');
        $collection = new ClassMetadataCollection(array($class));

        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $factory = new DisconnectedMetadataFactory($registry);

        $factory->findNamespaceAndPathForMetadata($collection, '/path/to/code');

        $this->assertEquals('\Vendor\Package', $collection->getNamespace());
    }
}
