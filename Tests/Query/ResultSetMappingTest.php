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

namespace Doctrine\Bundle\DoctrineBundle\Tests\Query;

use Doctrine\Bundle\DoctrineBundle\Query\ResultSetMapping;

class ResultSetMappingTest extends \PHPUnit_Framework_TestCase
{
    public function testGetConfig()
    {
        $config = $this->getMock('Doctrine\ORM\Configuration');
        $em = $this->getMockbuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $em->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $mapping = new ResultSetMapping($em);
        $this->assertSame($config, $mapping->getConfig());
    }

    public function testAddEntityResult()
    {
        $metaData = new \stdClass;
        $metaData->name = 'Example\Bundle\Entity\Entity';
        $metaDataFactory = $this->getMock(
            'Doctrine\ORM\Mapping\ClassMetadataFactory'
        );
        $metaDataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with('ExampleBundle:Entity')
            ->will($this->returnValue($metaData));

        $config = $this->getMock('Doctrine\ORM\Configuration');
        $config->expects($this->once())
            ->method('getClassMetadataFactoryName')
            ->will($this->returnValue($metaDataFactory));
        $em = $this->getMockbuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $em->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $mapping = new ResultSetMapping($em);
        $this->assertSame(
            $mapping,
            $mapping->addEntityResult('ExampleBundle:Entity', 'alias')
        );
        $this->assertSame($mapping->aliasMap['alias'], $metaData->name);
        $this->assertArrayHasKey('alias', $mapping->entityMappings);
    }

    public function testAddJoinedEntityResult()
    {
        $metaData = new \stdClass;
        $metaData->name = 'Example\Bundle\Entity\Entity';
        $metaDataFactory = $this->getMock(
            'Doctrine\ORM\Mapping\ClassMetadataFactory'
        );
        $metaDataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with('ExampleBundle:Entity')
            ->will($this->returnValue($metaData));

        $config = $this->getMock('Doctrine\ORM\Configuration');
        $config->expects($this->once())
            ->method('getClassMetadataFactoryName')
            ->will($this->returnValue($metaDataFactory));
        $em = $this->getMockbuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $em->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $mapping = new ResultSetMapping($em);
        $this->assertSame(
            $mapping,
            $mapping->addJoinedEntityResult(
                'ExampleBundle:Entity', 'alias', 'parentAlias', 'relation'
            )
        );
        $this->assertSame($mapping->aliasMap['alias'], $metaData->name);
        $this->assertArrayHasKey('alias', $mapping->parentAliasMap);
        $this->assertArrayHasKey('alias', $mapping->relationMap);
    }
}
