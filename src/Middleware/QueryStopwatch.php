<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Adapts the Doctrine bridge's hardcoded event name.
 *
 * @internal
 */
final class QueryStopwatch extends Stopwatch
{
    public function __construct(private readonly Stopwatch $stopwatch)
    {
        parent::__construct();
    }

    public function start(string $name, string|null $category = null): StopwatchEvent
    {
        return $this->stopwatch->start($name === 'doctrine' ? 'doctrine.query' : $name, $category);
    }

    public function stop(string $name): StopwatchEvent
    {
        return $this->stopwatch->stop($name === 'doctrine' ? 'doctrine.query' : $name);
    }
}
