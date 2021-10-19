<?php

namespace Doctrine\Bundle\DoctrineBundle\ORM\Mapping\Driver;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;

class DatabaseDriverFactory implements DatabaseDriverFactoryInterface
{
    public function create(AbstractSchemaManager $schemaManager): MappingDriver
    {
        return new DatabaseDriver($schemaManager);
    }
}
