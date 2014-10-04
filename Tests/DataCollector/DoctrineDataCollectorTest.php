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

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;

class DoctrineDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    const FIRST_ENTITY = 'TestBundle\Test\Entity\Test1';
    const SECOND_ENTITY = 'TestBundle\Test\Entity\Test2';

    public function testCollectEntities()
    {
        $manager = $this->getMock('Doctrine\ORM\EntityManagerInterface');
        $config = $this->getMock('Doctrine\ORM\Configuration');
        $factory = $this->getMockBuilder('Doctrine\Common\Persistence\Mapping\AbstractClassMetadataFactory')
            ->setMethods(array('getLoadedMetadata'))->getMockForAbstractClass();
        $collector = $this->createCollector(array('default' => $manager));

        $manager->expects($this->any())
            ->method('getMetadataFactory')
            ->will($this->returnValue($factory));
        $manager->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $config->expects($this->once())
            ->method('isSecondLevelCacheEnabled')
            ->will($this->returnValue(false));

        $metadatas = array(
            $this->createEntityMetadata(self::FIRST_ENTITY),
            $this->createEntityMetadata(self::SECOND_ENTITY),
            $this->createEntityMetadata(self::FIRST_ENTITY),
        );
        $factory->expects($this->once())
            ->method('getLoadedMetadata')
            ->will($this->returnValue($metadatas));

        $collector->collect(new Request(), new Response());

        $entities = $collector->getEntities();
        $this->assertArrayHasKey('default', $entities);
        $this->assertCount(2, $entities['default']);
    }

    /**
     * @param string $entityFQCN
     *
     * @return ClassMetadataInfo
     */
    private function createEntityMetadata($entityFQCN)
    {
        $metadata = new ClassMetadataInfo($entityFQCN);
        $metadata->name = $entityFQCN;

        return $metadata;
    }

    /**
     * @param array $managers
     *
     * @return DoctrineDataCollector
     */
    private function createCollector(array $managers)
    {
        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry
            ->expects($this->any())
            ->method('getConnectionNames')
            ->will($this->returnValue(array('default' => 'doctrine.dbal.default_connection')));
        $registry
            ->expects($this->any())
            ->method('getManagerNames')
            ->will($this->returnValue(array('default' => 'doctrine.orm.default_entity_manager')));
        $registry
            ->expects($this->any())
            ->method('getManagers')
            ->will($this->returnValue($managers));

        $collector = new DoctrineDataCollector($registry);

        return $collector;
    }
}
