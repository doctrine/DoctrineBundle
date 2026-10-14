<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOne;

use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/** @internal */
final class Driver extends AbstractDriverMiddleware
{
    public function __construct(
        DriverInterface $driver,
        private readonly NPlusOneGuard $guard,
        private readonly string $connectionName,
    ) {
        parent::__construct($driver);
    }

    /** @param array<string, mixed> $params */
    public function connect(array $params): ConnectionInterface
    {
        return new Connection(parent::connect($params), $this->guard, $this->connectionName);
    }
}
