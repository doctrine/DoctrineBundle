<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOne\Driver as NPlusOneDriver;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class NPlusOneGuardMiddleware implements Middleware, ConnectionNameAwareInterface
{
    private string $connectionName = 'default';

    public function __construct(private readonly NPlusOneGuard $guard)
    {
    }

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }

    public function wrap(Driver $driver): Driver
    {
        return new NPlusOneDriver($driver, $this->guard, $this->connectionName);
    }
}
