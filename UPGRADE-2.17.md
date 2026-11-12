UPGRADE FROM 2.16 to 2.17
=========================

DoctrineExtension
=================

Minor breaking change:
`Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension` no
longer extends
`Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension`.

Configuration
-------------

### The `doctrine.orm.entity_managers.some_em.report_fields_where_declared` configuration option is deprecated

This option is a no-op when using `doctrine/orm` 3 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine ORM 3.

### The `doctrine.dbal.connections.some_connection.disable_type_comments` configuration option is deprecated

This option is a no-op when using `doctrine/dbal` 4 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine DBAL 4.

### The `doctrine.dbal.connections.some_connection.use_savepoints` configuration option is deprecated

This option is a no-op when using `doctrine/dbal` 4 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine DBAL 4.

ConnectionFactory::createConnection() signature change
------------------------------------------------------

The signature of `ConnectionFactory::createConnection()` will change with
version 3.0 of the bundle.

As soon as you upgrade to Doctrine DBAL 4, you should use stop passing an event
manager argument.

```diff
- $connectionFactory->createConnection($params, $config, $eventManager, $mappingTypes)
+ $connectionFactory->createConnection($params, $config, $mappingTypes)
```

As a small breaking change, it is no longer fully possible to use named
arguments with that method until 3.0.
