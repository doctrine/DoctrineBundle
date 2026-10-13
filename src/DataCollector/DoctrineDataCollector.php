<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DataCollector;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\Logging\CacheLoggerChain;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector as BaseCollector;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Throwable;

use function array_map;
use function array_sum;
use function arsort;
use function assert;
use function count;
use function hash;
use function in_array;
use function preg_match;
use function preg_replace;
use function serialize;
use function strncasecmp;
use function usort;

/**
 * @phpstan-type DataType = array{
 *    caches: array{
 *       enabled: bool,
 *       counts: array<"puts"|"hits"|"misses", int>,
 *       log_enabled: bool,
 *       regions: array<"puts"|"hits"|"misses", array<string, int>>,
 *    },
 *    connections: list<string>,
 *    entities: array<string, array<class-string, array{class: class-string, file: false|string, line: false|int}>>,
 *    errors: array<string, array<class-string, list<string>>>,
 *    managers: list<string>,
 *    queries: array<string, list<array{
 *       executionMS: float,
 *       explainable: bool,
 *       sql: string,
 *       params: ?array<array-key, mixed>,
 *       runnable: bool,
 *       types: ?array<array-key, Type|int|string|null>
 *    }>>,
 *    entityCounts: array<string, array<class-string, int>>
 * }
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
 *    suspectedNPlusOne: bool,
 *    executionPercent?: float
 * }
 * @phpstan-type GroupedQueriesType = array<string, array<int, GroupedQueryItemType>>
 * @phpstan-property DataType $data
 */
class DoctrineDataCollector extends BaseCollector implements LateDataCollectorInterface
{
    /**
     * How many different parameter sets one SELECT has to be replayed with
     * before it is worth pointing at. Two is the shape of an N+1, but it is
     * also the shape of two unrelated lookups, so it would flag ordinary
     * pages.
     */
    private const int SUSPECTED_N_PLUS_ONE_THRESHOLD = 5;

    private int|null $invalidEntityCount = null;

    private int|null $managedEntityCount = null;

    /** @var GroupedQueriesType|null */
    private array|null $groupedQueries = null;

    /** @var array<string, array{EntityManagerInterface, list<ClassMetadata<object>>}> */
    private array $classesToValidate = [];

    /** @param list<string> $secondLevelCacheManagers The names of the managers that have the second level cache enabled */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly bool $shouldValidateSchema = true,
        DebugDataHolder|null $debugDataHolder = null,
        private readonly array $secondLevelCacheManagers = [],
    ) {
        if ($debugDataHolder === null) {
            $debugDataHolder = new DebugDataHolder();
        }

        parent::__construct($registry, $debugDataHolder);
    }

    public function collect(Request $request, Response $response, Throwable|null $exception = null): void
    {
        parent::collect($request, $response, $exception);

        $entities                = [];
        $entityCounts            = [];
        $this->classesToValidate = [];
        $caches                  = [
            'enabled' => false,
            'log_enabled' => false,
            'counts' => [
                'puts' => 0,
                'hits' => 0,
                'misses' => 0,
            ],
            'regions' => [
                'puts' => [],
                'hits' => [],
                'misses' => [],
            ],
        ];

        foreach ($this->registry->getManagers() as $name => $em) {
            assert($em instanceof EntityManagerInterface);
            if ($this->shouldValidateSchema) {
                $entities[$name] = [];
            }

            $entityCounts[$name] = [];

            // A manager that was not used during the request has nothing to report
            if (self::isUninitializedLazyObject($em)) {
                if (in_array($name, $this->secondLevelCacheManagers, true)) {
                    $caches['enabled'] = true;
                }

                continue;
            }

            if ($this->shouldValidateSchema) {
                $factory = $em->getMetadataFactory();
                $classes = [];

                foreach ($factory->getLoadedMetadata() as $class) {
                    if (isset($entities[$name][$class->getName()])) {
                        continue;
                    }

                    $classes[]                          = $class;
                    $r                                  = $class->getReflectionClass();
                    $entities[$name][$class->getName()] = [
                        'class' => $class->getName(),
                        'file' => $r->getFileName(),
                        'line' => $r->getStartLine(),
                    ];
                }

                $this->classesToValidate[$name] = [$em, $classes];
            }

            foreach ($em->getUnitOfWork()->getIdentityMap() as $className => $entityList) {
                $entityCounts[$name][$className] = count($entityList);
            }

            // Sort entities by count (in descending order)
            arsort($entityCounts[$name]);

            $emConfig   = $em->getConfiguration();
            $slcEnabled = $emConfig->isSecondLevelCacheEnabled();

            if (! $slcEnabled) {
                continue;
            }

            $caches['enabled'] = true;

            $cacheConfiguration = $emConfig->getSecondLevelCacheConfiguration();
            assert($cacheConfiguration instanceof CacheConfiguration);
            $cacheLoggerChain = $cacheConfiguration->getCacheLogger();
            assert($cacheLoggerChain instanceof CacheLoggerChain || $cacheLoggerChain === null);

            if (! $cacheLoggerChain || ! $cacheLoggerChain->getLogger('statistics')) {
                continue;
            }

            $cacheLoggerStats = $cacheLoggerChain->getLogger('statistics');
            assert($cacheLoggerStats instanceof StatisticsCacheLogger);
            $caches['log_enabled'] = true;

            $caches['counts']['puts']   += $cacheLoggerStats->getPutCount();
            $caches['counts']['hits']   += $cacheLoggerStats->getHitCount();
            $caches['counts']['misses'] += $cacheLoggerStats->getMissCount();

            foreach ($cacheLoggerStats->getRegionsPut() as $key => $value) {
                if (! isset($caches['regions']['puts'][$key])) {
                    $caches['regions']['puts'][$key] = 0;
                }

                $caches['regions']['puts'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsHit() as $key => $value) {
                if (! isset($caches['regions']['hits'][$key])) {
                    $caches['regions']['hits'][$key] = 0;
                }

                $caches['regions']['hits'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsMiss() as $key => $value) {
                if (! isset($caches['regions']['misses'][$key])) {
                    $caches['regions']['misses'][$key] = 0;
                }

                $caches['regions']['misses'][$key] += $value;
            }
        }

        $this->data['entities']     = $entities;
        $this->data['errors']       = [];
        $this->data['caches']       = $caches;
        $this->data['entityCounts'] = $entityCounts;
        $this->groupedQueries       = null;
    }

    public function lateCollect(): void
    {
        $errors = [];

        foreach ($this->classesToValidate as $name => [$em, $classes]) {
            $validator = new SchemaValidator($em);

            foreach ($classes as $class) {
                try {
                    $classErrors = $validator->validateClass($class);
                } catch (Throwable $e) {
                    // Profiles are saved on kernel.terminate: report the error instead of losing the profile
                    $classErrors = [$e->getMessage()];
                }

                if (empty($classErrors)) {
                    continue;
                }

                $errors[$name][$class->getName()] = $classErrors;
            }
        }

        $this->classesToValidate  = [];
        $this->data['errors']     = $errors;
        $this->invalidEntityCount = null;
    }

    public function reset(): void
    {
        parent::reset();

        $this->classesToValidate = [];
    }

    /** @return array<string, array<class-string, array{class: class-string, file: false|string, line: false|int}>> */
    public function getEntities(): array
    {
        return $this->data['entities'];
    }

    /** @return array<string, array<string, list<string>>> */
    public function getMappingErrors(): array
    {
        return $this->data['errors'];
    }

    public function getCacheHitsCount(): int
    {
        return $this->data['caches']['counts']['hits'];
    }

    public function getCachePutsCount(): int
    {
        return $this->data['caches']['counts']['puts'];
    }

    public function getCacheMissesCount(): int
    {
        return $this->data['caches']['counts']['misses'];
    }

    public function getCacheEnabled(): bool
    {
        return $this->data['caches']['enabled'];
    }

    /**
     * @return array<string, array<string, int>>
     * @phpstan-return array<"puts"|"hits"|"misses", array<string, int>>
     */
    public function getCacheRegions(): array
    {
        return $this->data['caches']['regions'];
    }

    /** @return array<string, int> */
    public function getCacheCounts(): array
    {
        return $this->data['caches']['counts'];
    }

    public function getInvalidEntityCount(): int
    {
        return $this->invalidEntityCount ??= array_sum(array_map('count', $this->data['errors']));
    }

    public function getManagedEntityCount(): int
    {
        if ($this->managedEntityCount === null) {
            $total = 0;
            foreach ($this->data['entityCounts'] as $entities) {
                $total += array_sum($entities);
            }

            $this->managedEntityCount = $total;
        }

        return $this->managedEntityCount;
    }

    /** @return array<string, array<class-string, int>> */
    public function getManagedEntityCountByClass(): array
    {
        return $this->data['entityCounts'];
    }

    /**
     * @return string[][]
     * @phpstan-return GroupedQueriesType
     */
    public function getGroupedQueries(): array
    {
        if ($this->groupedQueries !== null) {
            return $this->groupedQueries;
        }

        $this->groupedQueries = [];
        $totalExecutionMS     = 0;
        foreach ($this->data['queries'] as $connection => $queries) {
            $connectionGroupedQueries = [];
            $paramSets                = [];
            foreach ($queries as $i => $query) {
                $key = $query['sql'];
                if (! isset($connectionGroupedQueries[$key])) {
                    $connectionGroupedQueries[$key]                = $query;
                    $connectionGroupedQueries[$key]['executionMS'] = 0;
                    $connectionGroupedQueries[$key]['count']       = 0;
                    $connectionGroupedQueries[$key]['index']       = $i; // "Explain query" relies on query index in 'queries'.
                }

                $connectionGroupedQueries[$key]['executionMS'] += $query['executionMS'];
                $connectionGroupedQueries[$key]['count']++;
                $totalExecutionMS += $query['executionMS'];

                $paramSets[$key][$this->hashParams($query['params'] ?? [])] = true;
            }

            foreach ($connectionGroupedQueries as $key => $groupedQuery) {
                $paramSetCount = count($paramSets[$key]);

                $connectionGroupedQueries[$key]['paramSetCount']     = $paramSetCount;
                $connectionGroupedQueries[$key]['suspectedNPlusOne'] = $paramSetCount >= self::SUSPECTED_N_PLUS_ONE_THRESHOLD
                    && self::readsRows($groupedQuery['sql']);
            }

            usort($connectionGroupedQueries, static function ($a, $b) {
                if ($a['executionMS'] === $b['executionMS']) {
                    return 0;
                }

                return $a['executionMS'] < $b['executionMS'] ? 1 : -1;
            });
            $this->groupedQueries[$connection] = $connectionGroupedQueries;
        }

        foreach ($this->groupedQueries as &$queries) {
            foreach ($queries as &$query) {
                $query['executionPercent'] = $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
            }
        }

        return $this->groupedQueries;
    }

    /**
     * A write replayed with varying parameters is a batch, not an N+1: one
     * flush() inserting 50 rows is the same statement with 50 different
     * parameter sets, and no amount of eager fetching changes that.
     *
     * Statements do not always start with their verb, though: a CTE opens with
     * WITH, a compound select can be parenthesised, and tracing middlewares
     * prepend a comment. Step over all of that before looking.
     */
    private static function readsRows(string $sql): bool
    {
        $sql = preg_replace('{^(?:\s|/\*.*?\*/|(?:--|\#)[^\n]*(?:\n|$)|\()+}s', '', $sql) ?? $sql;

        if (strncasecmp($sql, 'SELECT', 6) === 0) {
            return true;
        }

        // A common table expression modifies rows when its body does, which on
        // PostgreSQL it may. Nothing short of parsing says where that body
        // ends, so a WITH carrying any of those words is left alone.
        return strncasecmp($sql, 'WITH', 4) === 0
            && preg_match('{\b(?:INSERT|UPDATE|DELETE|MERGE)\b}i', $sql) === 0;
    }

    /**
     * Returns a fixed-size identity for a set of query parameters.
     *
     * Counting the distinct identities of a group tells apart the same
     * statement repeated as is from the same statement replayed with varying
     * parameters, which is the typical signature of N+1 queries caused by ORM
     * lazy loading.
     *
     * Parameters are read after sanitization, where an object that has no
     * string form is rendered as a notice rather than a value. Such objects
     * are indistinguishable from each other here, so a statement bound with
     * one counts as a single parameter set and goes unreported.
     */
    private function hashParams(mixed $params): string
    {
        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return hash('xxh128', serialize($params));
    }

    private function executionTimePercentage(float $executionTimeMS, float $totalExecutionTimeMS): float
    {
        if (! $totalExecutionTimeMS) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }

    public function getGroupedQueryCount(): int
    {
        $count = 0;
        foreach ($this->getGroupedQueries() as $connectionGroupedQueries) {
            $count += count($connectionGroupedQueries);
        }

        return $count;
    }

    private static function isUninitializedLazyObject(object $object): bool
    {
        if ($object instanceof LazyObjectInterface) {
            return ! $object->isLazyObjectInitialized();
        }

        return (new ReflectionClass($object))->isUninitializedLazyObject($object);
    }
}
