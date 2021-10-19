<?php

namespace Doctrine\Bundle\DoctrineBundle\ORM\Mapping\Driver;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;

interface DatabaseDriverFactoryInterface
{
    public function create(AbstractSchemaManager $schemaManager): MappingDriver;
}
