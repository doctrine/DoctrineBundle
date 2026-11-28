<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DataCollector;

use Doctrine\ORM\HydrationObserver;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

use function array_key_last;
use function array_pop;
use function array_unique;
use function array_values;
use function hrtime;
use function implode;
use function sort;

/**
 * @internal
 *
 * @phpstan-type HydrationStats = array{
 *    hydrator: class-string<AbstractHydrator>,
 *    entities: list<class-string>,
 *    operations: int,
 *    results: int|null,
 *    time: float
 * }
 */
final class HydrationProfiler implements HydrationObserver
{
    private const int MAX_TIMELINE_PERIODS = 1000;

    /**
     * @var list<array{
     *    key: string,
     *    startedAt: float,
     *    nestedTime: float,
     *    event: StopwatchEvent|null
     * }>
     */
    private array $active = [];

    /**
     * Totals indexed by hydrator class and the sorted set of mapped entity classes.
     *
     * @var array<string, HydrationStats>
     */
    private array $hydrations = [];

    private int $timelinePeriods = 0;

    public function __construct(private readonly Stopwatch|null $stopwatch = null)
    {
    }

    /**
     * @param class-string<AbstractHydrator> $hydratorClass
     * @param array<string, class-string>    $entityClasses
     */
    public function start(string $hydratorClass, array $entityClasses): void
    {
        $entities = array_values(array_unique($entityClasses));
        sort($entities);
        $key = $hydratorClass . ':' . implode(',', $entities);

        $this->hydrations[$key] ??= [
            'hydrator' => $hydratorClass,
            'entities' => $entities,
            'operations' => 0,
            'results' => 0,
            'time' => 0.0,
        ];

        $event = null;

        // Streamed hydration can report a period per result.
        if ($this->active === [] && $this->stopwatch !== null && $this->timelinePeriods < self::MAX_TIMELINE_PERIODS) {
            $event = $this->stopwatch->start('doctrine.orm.hydration', 'doctrine');
            ++$this->timelinePeriods;
        }

        $this->active[] = ['key' => $key, 'startedAt' => (float) hrtime(true), 'nestedTime' => 0.0, 'event' => $event];
    }

    public function end(int|null $resultCount): void
    {
        $finishedAt = (float) hrtime(true);
        $hydration  = array_pop($this->active);

        if ($hydration === null) {
            return;
        }

        $hydration['event']?->stop();
        $elapsed = $finishedAt - $hydration['startedAt'];
        $parent  = array_key_last($this->active);

        if ($parent !== null) {
            $this->active[$parent]['nestedTime'] += $elapsed;
        }

        $stats =& $this->hydrations[$hydration['key']];
        ++$stats['operations'];
        $stats['time']   += ($elapsed - $hydration['nestedTime']) / 1e9;
        $stats['results'] = $stats['results'] === null || $resultCount === null ? null : $stats['results'] + $resultCount;
    }

    /**
     * Returns a snapshot of the request's hydration totals.
     *
     * @return list<HydrationStats>
     */
    public function getHydrations(): array
    {
        return array_values($this->hydrations);
    }

    public function reset(): void
    {
        $this->active          = [];
        $this->hydrations      = [];
        $this->timelinePeriods = 0;
    }
}
