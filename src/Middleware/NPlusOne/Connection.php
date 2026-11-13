<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOne;

use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement as StatementInterface;

/** @internal */
final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $connection,
        private readonly NPlusOneGuard $guard,
        private readonly string $connectionName,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): StatementInterface
    {
        return new Statement(parent::prepare($sql), $this->guard, $this->connectionName, $sql);
    }
}
