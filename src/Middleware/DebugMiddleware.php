<?php

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Driver;
use Symfony\Component\Stopwatch\Stopwatch;

class DebugMiddleware implements Middleware, ConnectionNameAwareInterface
{
    private string $connectionName = 'default';

    public function __construct(
        private readonly DebugDataHolder $debugDataHolder,
        private readonly Stopwatch|null $stopwatch,
    ) {
    }

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->debugDataHolder, $this->stopwatch, $this->connectionName);
    }
}
