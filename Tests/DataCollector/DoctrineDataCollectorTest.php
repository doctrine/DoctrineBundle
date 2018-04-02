<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoctrineDataCollectorTest extends TestCase
{
    const FIRST_ENTITY  = 'TestBundle\Test\Entity\Test1';
    const SECOND_ENTITY = 'TestBundle\Test\Entity\Test2';

    public function testCollectEntities()
    {
        $manager   = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $config    = $this->getMockBuilder('Doctrine\ORM\Configuration')->getMock();
        $factory   = $this->getMockBuilder('Doctrine\Common\Persistence\Mapping\AbstractClassMetadataFactory')
            ->setMethods(['getLoadedMetadata'])->getMockForAbstractClass();
        $collector = $this->createCollector(['default' => $manager]);

        $manager->expects($this->any())
            ->method('getMetadataFactory')
            ->will($this->returnValue($factory));
        $manager->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        if (method_exists($config, 'isSecondLevelCacheEnabled')) {
            $config->expects($this->once())
                ->method('isSecondLevelCacheEnabled')
                ->will($this->returnValue(false));
        }

        $metadatas = [
            $this->createEntityMetadata(self::FIRST_ENTITY),
            $this->createEntityMetadata(self::SECOND_ENTITY),
            $this->createEntityMetadata(self::FIRST_ENTITY),
        ];
        $factory->expects($this->once())
            ->method('getLoadedMetadata')
            ->will($this->returnValue($metadatas));

        $collector->collect(new Request(), new Response());

        $entities = $collector->getEntities();
        $this->assertArrayHasKey('default', $entities);
        $this->assertCount(2, $entities['default']);
    }

    public function testGetGroupedQueries()
    {
        $logger            = $this->getMockBuilder('Doctrine\DBAL\Logging\DebugStack')->getMock();
        $logger->queries   = [];
        $logger->queries[] = [
            'sql' => 'SELECT * FROM foo WHERE bar = :bar',
            'params' => [':bar' => 1],
            'executionMS' => 32,
        ];
        $logger->queries[] = [
            'sql' => 'SELECT * FROM foo WHERE bar = :bar',
            'params' => [':bar' => 2],
            'executionMS' => 25,
        ];
        $collector         = $this->createCollector([]);
        $collector->addLogger('default', $logger);
        $collector->collect(new Request(), new Response());
        $groupedQueries = $collector->getGroupedQueries();
        $this->assertCount(1, $groupedQueries['default']);
        $this->assertSame('SELECT * FROM foo WHERE bar = :bar', $groupedQueries['default'][0]['sql']);
        $this->assertSame(2, $groupedQueries['default'][0]['count']);

        $logger->queries[] = [
            'sql' => 'SELECT * FROM bar',
            'params' => [],
            'executionMS' => 25,
        ];
        $collector->collect(new Request(), new Response());
        $groupedQueries = $collector->getGroupedQueries();
        $this->assertCount(2, $groupedQueries['default']);
        $this->assertSame('SELECT * FROM bar', $groupedQueries['default'][1]['sql']);
        $this->assertSame(1, $groupedQueries['default'][1]['count']);
    }

    /**
     * @param string $entityFQCN
     *
     * @return ClassMetadataInfo
     */
    private function createEntityMetadata($entityFQCN)
    {
        $metadata            = new ClassMetadataInfo($entityFQCN);
        $metadata->name      = $entityFQCN;
        $metadata->reflClass = new \ReflectionClass('stdClass');

        return $metadata;
    }

    /**
     * @param array $managers
     *
     * @return DoctrineDataCollector
     */
    private function createCollector(array $managers)
    {
        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $registry
            ->expects($this->any())
            ->method('getConnectionNames')
            ->will($this->returnValue(['default' => 'doctrine.dbal.default_connection']));
        $registry
            ->expects($this->any())
            ->method('getManagerNames')
            ->will($this->returnValue(['default' => 'doctrine.orm.default_entity_manager']));
        $registry
            ->expects($this->any())
            ->method('getManagers')
            ->will($this->returnValue($managers));

        $collector = new DoctrineDataCollector($registry);

        return $collector;
    }
}
