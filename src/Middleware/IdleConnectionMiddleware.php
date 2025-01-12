<?php

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use ArrayObject;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver as IdleConnectionDriver;

class IdleConnectionMiddleware implements Middleware, ConnectionNameAwareInterface
{
    private string $connectionName;

    /**
     * @param ArrayObject<string, int> $connectionExpiries
     * @param array<string, int>       $ttlByConnection
     */
    public function __construct(
        private readonly ArrayObject $connectionExpiries,
        private readonly array $ttlByConnection,
    ) {
    }

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }

    public function wrap(Driver $driver): IdleConnectionDriver
    {
        return new IdleConnectionDriver(
            $driver,
            $this->connectionExpiries,
            $this->ttlByConnection[$this->connectionName],
            $this->connectionName,
        );
    }
}
