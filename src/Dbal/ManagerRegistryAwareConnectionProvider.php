<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\Persistence\AbstractManagerRegistry;

use function assert;

class ManagerRegistryAwareConnectionProvider implements ConnectionProvider
{
    public function __construct(
        private readonly AbstractManagerRegistry $managerRegistry,
    ) {
    }

    public function getDefaultConnection(): Connection
    {
        $connection = $this->managerRegistry->getConnection();
        assert($connection instanceof Connection);

        return $connection;
    }

    public function getConnection(string $name): Connection
    {
        $connection = $this->managerRegistry->getConnection($name);
        assert($connection instanceof Connection);

        return $connection;
    }
}
