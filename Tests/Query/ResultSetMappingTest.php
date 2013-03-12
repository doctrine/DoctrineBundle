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
    protected $metadata, $mapping;

    public function setUp()
    {
        $this->metadata = new \stdClass;
        $this->metadata->name = 'Example\Bundle\Entity\Entity';

        $metadataFactory = $this->getMock(
            'Doctrine\ORM\Mapping\ClassMetadataFactory'
        );
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with('ExampleBundle:Entity')
            ->will($this->returnValue($this->metadata));

        $config = $this->getMock('Doctrine\ORM\Configuration');
        $config->expects($this->once())
            ->method('getClassMetadataFactoryName')
            ->will($this->returnValue($metadataFactory));

        $em = $this->getMockbuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $em->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $this->mapping = new ResultSetMapping($em);
    }

    public function testAddEntityResult()
    {
        $this->assertSame(
            $this->mapping,
            $this->mapping->addEntityResult('ExampleBundle:Entity', 'alias')
        );
        $this->assertSame(
            $this->mapping->aliasMap['alias'],
            $this->metadata->name
        );
        $this->assertArrayHasKey('alias', $this->mapping->entityMappings);
    }

    public function testAddJoinedEntityResult()
    {
        $this->assertSame(
            $this->mapping,
            $this->mapping->addJoinedEntityResult(
                'ExampleBundle:Entity', 'alias', 'parentAlias', 'relation'
            )
        );
        $this->assertSame(
            $this->mapping->aliasMap['alias'],
            $this->metadata->name
        );
        $this->assertArrayHasKey('alias', $this->mapping->parentAliasMap);
        $this->assertArrayHasKey('alias', $this->mapping->relationMap);
    }
}
