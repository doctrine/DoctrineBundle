<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Profiling;

use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

use function array_column;
use function array_sum;
use function count;

/**
 * Keeps the lazy loads of the request, one record per load, with the entities
 * it hydrated.
 *
 * The postLoad listener fills this holder during the request and the data
 * collector reads it when it collects the profile.
 *
 * @internal
 */
final class LazyLoadDataHolder implements ResetInterface
{
    /** @var list<array{kind: 'single'|'collection', classes: array<string, int>, entities: int, connection: string|null, query: int|null}> */
    private array $lazyLoads = [];

    /** @var WeakMap<object, int> Index of the record of each collection being loaded */
    private WeakMap $loads;

    public function __construct()
    {
        $this->loads = new WeakMap();
    }

    /**
     * @param LazyLoadDetector::SINGLE|LazyLoadDetector::COLLECTION $kind
     * @param object|null                                           $load       The collection being loaded, to group its entities
     * @param string|null                                           $connection The connection of the query that loaded the entities
     * @param int|null                                              $query      The position of that query in the connection
     */
    public function record(
        string $class,
        string $kind,
        object|null $load = null,
        string|null $connection = null,
        int|null $query = null,
    ): void {
        if ($load !== null && isset($this->loads[$load])) {
            $index    = $this->loads[$load];
            $lazyLoad = $this->lazyLoads[$index];

            $lazyLoad['classes'][$class] = ($lazyLoad['classes'][$class] ?? 0) + 1;
            $lazyLoad['entities']++;

            $this->lazyLoads[$index] = $lazyLoad;

            return;
        }

        $index = count($this->lazyLoads);

        $this->lazyLoads[] = [
            'kind' => $kind,
            'classes' => [$class => 1],
            'entities' => 1,
            'connection' => $connection,
            'query' => $query,
        ];

        if ($load === null) {
            return;
        }

        $this->loads[$load] = $index;
    }

    /** @return list<array{kind: 'single'|'collection', classes: array<string, int>, entities: int, connection: string|null, query: int|null}> */
    public function getLazyLoads(): array
    {
        return $this->lazyLoads;
    }

    public function getCount(): int
    {
        return array_sum(array_column($this->lazyLoads, 'entities'));
    }

    public function reset(): void
    {
        $this->lazyLoads = [];
        $this->loads     = new WeakMap();
    }
}
