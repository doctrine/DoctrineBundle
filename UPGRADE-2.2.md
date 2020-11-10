UPGRADE FROM 2.1 to 2.2
=======================

Commands
--------

 * `doctrine:query:sql` command has been deprecated. Use `dbal:run-sql` command instead.
 
Configuration
--------
 * Following the [changes in DBAL 2.11](https://github.com/doctrine/dbal/pull/4054), we deprecated following configuration keys:
    * `doctrine.dbal.slaves`. Use `doctrine.dbal.replicas`
    * `doctrine.dbal.keep_slave`. Use `doctrine.dbal.keep_replica`
    
    Similarly, if you use XML configuration, please replace `<slave>` with `<replica>`.
