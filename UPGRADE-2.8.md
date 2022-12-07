UPGRADE FROM 2.7 to 2.8
=======================

Dependencies
-------
 * support for PHP 7.1, 7.2 and 7.3 has been dropped. 
 * support for `doctrine/dbal` 2 has been dropped. 

Commands
--------
 * Support for using Shards has been removed from `Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand` as it's not supported using DBAL 3.
 * Support for using Shards has been removed from `Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand` as it's not supported using DBAL 3.
 * Support for using Shards has been removed from `Doctrine\Bundle\DoctrineBundle\Command\ImportMappingDoctrineCommand` as it's not supported using DBAL 3.
 * The `Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineCommand` command has been removed as it's not supported using DBAL 3.
 * All ORM proxy commands in the namespace `Doctrine\Bundle\DoctrineBundle\Command\Proxy\` have been deprecated. Use their corresponding ORM command class directly instead.

Configuration
--------
 * The `shard_manager_class`, `shard_choser`, `shard_choser_service` and `shards` configuration keys have been removed as they are not supported using DBAL 3

Dependency-Injection
--------
* The `doctrine.dbal.logging`, `doctrine.dbal.logger.chain`, `doctrine.dbal.logger.profiling` and `doctrine.dbal.logger.backtrace` services have been removed
* The `doctrine.dbal.logger.chain.class`, `doctrine.dbal.logger.profiling.class` and `doctrine.dbal.logger.class` parameters have been removed
