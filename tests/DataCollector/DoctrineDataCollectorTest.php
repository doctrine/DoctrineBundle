<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use stdClass;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function array_keys;
use function interface_exists;

/**
 * @phpstan-type GroupedQueryItemType = array{
 *    executionMS: float,
 *    explainable: bool,
 *    sql: string,
 *    params: ?array<array-key, mixed>,
 *    runnable: bool,
 *    types: ?array<array-key, Type|int|string|null>,
 *    count: int,
 *    index: int,
 *    paramSetCount: int,
 *    executionPercent?: float
 * }
 * @phpstan-type GroupedQueriesType = array<string, array<int, GroupedQueryItemType>>
 */

class DoctrineDataCollectorTest extends TestCase
{
    public const string FIRST_ENTITY  = 'TestBundle\Test\Entity\Test1';
    public const string SECOND_ENTITY = 'TestBundle\Test\Entity\Test2';

    public function testCollectEntities(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager    = $this->createStub(EntityManagerInterface::class);
        $config     = $this->createMock(Configuration::class);
        $factory    = $this->createMock(ClassMetadataFactory::class);
        $collector  = $this->createCollector(['default' => $manager], true, $this->createStub(DebugDataHolder::class));
        $unitOfWork = $this->createStub(UnitOfWork::class);

        $manager->method('getMetadataFactory')->willReturn($factory);
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $unitOfWork->method('getIdentityMap')->willReturn([
            self::FIRST_ENTITY => [new stdClass()],
            self::SECOND_ENTITY => [new stdClass(), new stdClass()],
        ]);

        $config->expects($this->once())
            ->method('isSecondLevelCacheEnabled')
            ->willReturn(false);

        $metadatas = [
            /** @phpstan-ignore argument.type */
            $this->createEntityMetadata(self::FIRST_ENTITY),
            /** @phpstan-ignore argument.type */
            $this->createEntityMetadata(self::SECOND_ENTITY),
            /** @phpstan-ignore argument.type */
            $this->createEntityMetadata(self::FIRST_ENTITY),
        ];
        $factory->expects($this->once())
            ->method('getLoadedMetadata')
            ->willReturn($metadatas);

        $collector->collect(new Request(), new Response());

        $entities = $collector->getEntities();
        $this->assertArrayHasKey('default', $entities);
        $this->assertCount(2, $entities['default']);
        $this->assertSame(3, $collector->getManagedEntityCount());
    }

    public function testDoesNotCollectEntities(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager    = $this->createMock(EntityManager::class);
        $config     = $this->createStub(Configuration::class);
        $collector  = $this->createCollector(['default' => $manager], false, $this->createStub(DebugDataHolder::class));
        $unitOfWork = $this->createStub(UnitOfWork::class);

        $manager->expects($this->never())->method('getMetadataFactory');
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $unitOfWork->method('getIdentityMap')->willReturn([]);

        $collector->collect(new Request(), new Response());

        $this->assertEmpty($collector->getMappingErrors());
        $this->assertEmpty($collector->getEntities());
    }

    public function testCollectDoesNotInitializeUnusedLazyManagers(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->createXmlBundleTestContainer();
        $em        = $container->get('doctrine.orm.default_entity_manager');
        $collector = $container->get('data_collector.doctrine');
        $this->assertInstanceOf(DoctrineDataCollector::class, $collector);
        $this->assertTrue(self::isUninitializedLazyObject($em));

        $collector->collect(new Request(), new Response());
        $this->assertTrue(self::isUninitializedLazyObject($em));

        $collector->lateCollect();
        $this->assertTrue(self::isUninitializedLazyObject($em));

        $this->assertSame(['default' => []], $collector->getEntities());
        $this->assertSame([], $collector->getMappingErrors());
        $this->assertSame(['default' => []], $collector->getManagedEntityCountByClass());
        $this->assertFalse($collector->getCacheEnabled());
    }

    public function testCollectReportsSecondLevelCacheOfUnusedLazyManagers(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager   = (new ReflectionClass(EntityManager::class))->newLazyGhost(static function (): never {
            self::fail('The entity manager should not be initialized.');
        });
        $collector = $this->createCollector(['default' => $manager], true, null, ['default']);

        $collector->collect(new Request(), new Response());

        $this->assertTrue($collector->getCacheEnabled());
        $this->assertSame(['puts' => 0, 'hits' => 0, 'misses' => 0], $collector->getCacheCounts());
    }

    public function testLateCollectValidatesMapping(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager    = $this->createStub(EntityManagerInterface::class);
        $config     = $this->createStub(Configuration::class);
        $factory    = $this->createStub(ClassMetadataFactory::class);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $collector  = $this->createCollector(['default' => $manager]);

        /** @phpstan-ignore argument.type */
        $metadata = $this->createEntityMetadata(self::FIRST_ENTITY);
        $metadata->mapManyToOne(['fieldName' => 'missing', 'targetEntity' => 'TestBundle\Test\Entity\Missing']);

        $manager->method('getMetadataFactory')->willReturn($factory);
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $factory->method('getLoadedMetadata')->willReturn([$metadata]);
        $unitOfWork->method('getIdentityMap')->willReturn([]);

        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $this->assertSame([self::FIRST_ENTITY], array_keys($collector->getEntities()['default']));
        $this->assertSame(1, $collector->getInvalidEntityCount());
        $this->assertSame(
            ['default' => [self::FIRST_ENTITY => ["The target entity 'TestBundle\Test\Entity\Missing' specified on TestBundle\Test\Entity\Test1#missing is unknown or not an entity."]]],
            $collector->getMappingErrors(),
        );
    }

    public function testLateCollectReportsMappingExceptions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $manager    = $this->createStub(EntityManagerInterface::class);
        $config     = $this->createStub(Configuration::class);
        $factory    = $this->createStub(ClassMetadataFactory::class);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $collector  = $this->createCollector(['default' => $manager]);

        /** @phpstan-ignore argument.type */
        $metadata = $this->createEntityMetadata(self::FIRST_ENTITY);
        $metadata->mapManyToOne(['fieldName' => 'broken', 'targetEntity' => stdClass::class]);

        $manager->method('getMetadataFactory')->willReturn($factory);
        $manager->method('getConfiguration')->willReturn($config);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $factory->method('getLoadedMetadata')->willReturn([$metadata]);
        $factory->method('getMetadataFor')->willThrowException(new MappingException('Broken mapping.'));
        $unitOfWork->method('getIdentityMap')->willReturn([]);

        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $this->assertSame(['default' => [self::FIRST_ENTITY => ['Broken mapping.']]], $collector->getMappingErrors());
    }

    public function testGetGroupedQueries(): void
    {
        $debugDataHolder = $this->createStub(DebugDataHolder::class);

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

    public function testGetGroupedQueriesExecutionPercent(): void
    {
        $debugDataHolder = $this->createStub(DebugDataHolder::class);

        $queries = [
            'default' => [
                [
                    'sql' => 'SELECT * FROM foo',
                    'params' => [],
                    'types' => null,
                    'executionMS' => 100.0,
                ],
                [
                    'sql' => 'SELECT * FROM bar',
                    'params' => [],
                    'types' => null,
                    'executionMS' => 200.0,
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

        $this->assertCount(2, $groupedQueries['default']);

        $firstItem = $groupedQueries['default'][0];
        $this->assertEqualsWithDelta(66.667, $firstItem['executionPercent'] ?? null, 0.001);
        $this->assertSame('SELECT * FROM bar', $firstItem['sql']);
        $secondItem = $groupedQueries['default'][1];
        $this->assertEqualsWithDelta(33.333, $secondItem['executionPercent'] ?? null, 0.001);
        $this->assertSame('SELECT * FROM foo', $secondItem['sql']);
    }

    public function testGetGroupedQueriesCountsDistinctParameterSets(): void
    {
        $debugDataHolder = $this->createStub(DebugDataHolder::class);

        $queries = [
            'default' => [
                [
                    'sql' => 'SELECT * FROM post WHERE author_id = :id',
                    'params' => [':id' => 1],
                    'types' => null,
                    'executionMS' => 1.0,
                ],
                [
                    'sql' => 'SELECT * FROM post WHERE author_id = :id',
                    'params' => [':id' => 2],
                    'types' => null,
                    'executionMS' => 1.0,
                ],
                [
                    'sql' => 'SELECT * FROM author WHERE id = :id',
                    'params' => [':id' => 1],
                    'types' => null,
                    'executionMS' => 1.0,
                ],
                [
                    'sql' => 'SELECT * FROM author WHERE id = :id',
                    'params' => [':id' => 1],
                    'types' => null,
                    'executionMS' => 1.0,
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

        $this->assertSame('SELECT * FROM post WHERE author_id = :id', $groupedQueries['default'][0]['sql']);
        $this->assertSame(2, $groupedQueries['default'][0]['count']);
        $this->assertSame(2, $groupedQueries['default'][0]['paramSetCount']);

        $this->assertSame('SELECT * FROM author WHERE id = :id', $groupedQueries['default'][1]['sql']);
        $this->assertSame(2, $groupedQueries['default'][1]['count']);
        $this->assertSame(1, $groupedQueries['default'][1]['paramSetCount']);
    }

    public function testGetGroupedQueriesWithUnserializableParameters(): void
    {
        $debugDataHolder = $this->createStub(DebugDataHolder::class);

        $queries = [
            'default' => [
                [
                    'sql' => 'SELECT * FROM post WHERE author_id = :id',
                    'params' => [':id' => 1, ':at' => static fn () => null],
                    'types' => null,
                    'executionMS' => 1.0,
                ],
                [
                    'sql' => 'SELECT * FROM post WHERE author_id = :id',
                    'params' => [':id' => 2, ':at' => static fn () => null],
                    'types' => null,
                    'executionMS' => 1.0,
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

        $this->assertSame(2, $groupedQueries['default'][0]['count']);
        $this->assertSame(2, $groupedQueries['default'][0]['paramSetCount']);
    }

    /**
     * @param class-string $entityFQCN
     *
     * @return ClassMetadata<object>
     */
    private function createEntityMetadata(string $entityFQCN): ClassMetadata
    {
        $metadata       = new ClassMetadata($entityFQCN);
        $metadata->name = $entityFQCN;
        /** @var ReflectionClass<object> $stdClassReflection */
        $stdClassReflection  = new ReflectionClass('stdClass');
        $metadata->reflClass = $stdClassReflection;

        return $metadata;
    }

    /**
     * @param array<string, object> $managers
     * @param list<string>          $secondLevelCacheManagers
     */
    private function createCollector(
        array $managers,
        bool $shouldValidateSchema = true,
        DebugDataHolder|null $debugDataHolder = null,
        array $secondLevelCacheManagers = [],
    ): DoctrineDataCollector {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getConnectionNames')
            ->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $registry->method('getManagerNames')
            ->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManagers')->willReturn($managers);

        return new DoctrineDataCollector($registry, $shouldValidateSchema, $debugDataHolder, $secondLevelCacheManagers);
    }

    private static function isUninitializedLazyObject(object $object): bool
    {
        if ($object instanceof LazyObjectInterface) {
            return ! $object->isLazyObjectInitialized();
        }

        return (new ReflectionClass($object))->isUninitializedLazyObject($object);
    }
}
