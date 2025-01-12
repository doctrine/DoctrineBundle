<?php

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\Persistence\AbstractManagerRegistry;

class ManagerRegistryAwareConnectionProvider implements ConnectionProvider
{
    public function __construct(
        private readonly AbstractManagerRegistry $managerRegistry,
    ) {
    }

    public function getDefaultConnection(): Connection
    {
        return $this->managerRegistry->getConnection();
    }

    public function getConnection(string $name): Connection
    {
        return $this->managerRegistry->getConnection($name);
    }
}
