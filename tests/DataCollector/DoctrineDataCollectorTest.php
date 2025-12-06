<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DataCollector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function interface_exists;

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

    /**
     * @param class-string $entityFQCN
     *
     * @return ClassMetadata<object>
     */
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
        DebugDataHolder|null $debugDataHolder = null,
    ): DoctrineDataCollector {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getConnectionNames')
            ->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $registry->method('getManagerNames')
            ->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManagers')->willReturn($managers);

        return new DoctrineDataCollector($registry, $shouldValidateSchema, $debugDataHolder);
    }
}
