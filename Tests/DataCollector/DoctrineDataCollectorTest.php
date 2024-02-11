<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
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

        $manager   = $this->createMock(EntityManagerInterface::class);
        $config    = $this->createMock(Configuration::class);
        $factory   = $this->createMock(ClassMetadataFactory::class);
        $collector = $this->createCollector(['default' => $manager], true, $this->createMock(DebugDataHolder::class));

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

        $manager   = $this->createMock(EntityManager::class);
        $config    = $this->createMock(Configuration::class);
        $collector = $this->createCollector(['default' => $manager], false, $this->createMock(DebugDataHolder::class));

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
        $debugDataHolder = $this->createMock(DebugDataHolder::class);

        $queries = [
            'default' => [
                [
                    'sql' => 'SELECT * FROM foo WHERE bar = :bar',
                    'params' => [':bar' => 1],
                    'types' => null,
                    'executionMS' => 32,
                ],
                [
                    'sql' => 'SELECT * FROM foo WHERE bar = :bar',
                    'params' => [':bar' => 2],
                    'types' => null,
                    'executionMS' => 25,
                ],
            ],
        ];

        $debugDataHolder->method('getData')
            ->willReturnCallback(static function () use (&$queries) {
                return $queries;
            });

        $collector = $this->createCollector([], true, $debugDataHolder);
        $collector->collect(new Request(), new Response());
        $groupedQueries = $collector->getGroupedQueries();
        $this->assertCount(1, $groupedQueries['default']);
        $this->assertSame('SELECT * FROM foo WHERE bar = :bar', $groupedQueries['default'][0]['sql']);
        $this->assertSame(2, $groupedQueries['default'][0]['count']);

        $queries['default'][] = [
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

    private function createEntityMetadata(string $entityFQCN): ClassMetadata
    {
        $metadata            = new ClassMetadata($entityFQCN);
        $metadata->name      = $entityFQCN;
        $metadata->reflClass = new ReflectionClass('stdClass');

        return $metadata;
    }

    /** @param array<string, object> $managers */
    private function createCollector(
        array $managers,
        bool $shouldValidateSchema = true,
        ?DebugDataHolder $debugDataHolder = null
    ): DoctrineDataCollector {
        $registry = $this->createMock(ManagerRegistry::class);
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

        return new DoctrineDataCollector($registry, $shouldValidateSchema, $debugDataHolder);
    }
}
