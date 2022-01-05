<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function interface_exists;

class DoctrineDataCollectorTest extends TestCase
{
    public const FIRST_ENTITY  = 'TestBundle\Test\Entity\Test1';
    public const SECOND_ENTITY = 'TestBundle\Test\Entity\Test2';

    public function testCollectEntities(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager   = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $config    = $this->getMockBuilder('Doctrine\ORM\Configuration')->getMock();
        $factory   = $this->getMockBuilder(ClassMetadataFactory::class)->setMethods(['getLoadedMetadata'])->getMock();
        $collector = $this->createCollector(['default' => $manager]);

        $manager->expects($this->any())
            ->method('getMetadataFactory')
            ->will($this->returnValue($factory));
        $manager->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($config));

        $config->expects($this->once())
            ->method('isSecondLevelCacheEnabled')
            ->will($this->returnValue(false));

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

    public function testDoesNotCollectEntities(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager   = $this->createMock('Doctrine\ORM\EntityManager');
        $config    = $this->createMock('Doctrine\ORM\Configuration');
        $collector = $this->createCollector(['default' => $manager], false);

        $manager->expects($this->never())
            ->method('getMetadataFactory');
        $manager->method('getConfiguration')
            ->will($this->returnValue($config));

        $collector->collect(new Request(), new Response());

        $this->assertEmpty($collector->getMappingErrors());
        $this->assertEmpty($collector->getEntities());
    }

    public function testGetGroupedQueries(): void
    {
        $logger            = $this->getMockBuilder(DebugStack::class)->getMock();
        $logger->queries   = [];
        $logger->queries[] = [
            'sql' => 'SELECT * FROM foo WHERE bar = :bar',
            'params' => [':bar' => 1],
            'types' => null,
            'executionMS' => 32,
        ];
        $logger->queries[] = [
            'sql' => 'SELECT * FROM foo WHERE bar = :bar',
            'params' => [':bar' => 2],
            'types' => null,
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
            'types' => null,
            'executionMS' => 25,
        ];
        $collector->collect(new Request(), new Response());
        $groupedQueries = $collector->getGroupedQueries();
        $this->assertCount(2, $groupedQueries['default']);
        $this->assertSame('SELECT * FROM bar', $groupedQueries['default'][1]['sql']);
        $this->assertSame(1, $groupedQueries['default'][1]['count']);
    }

    private function createEntityMetadata(string $entityFQCN): ClassMetadataInfo
    {
        $metadata            = new ClassMetadataInfo($entityFQCN);
        $metadata->name      = $entityFQCN;
        $metadata->reflClass = new ReflectionClass('stdClass');

        return $metadata;
    }

    /** @param array<string, object> $managers */
    private function createCollector(array $managers, bool $shouldValidateSchema = true): DoctrineDataCollector
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
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

        return new DoctrineDataCollector($registry, $shouldValidateSchema);
    }
}
