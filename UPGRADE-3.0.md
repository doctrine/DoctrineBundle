UPGRADE FROM 2.x to 3.0
=======================

Compatibility
-------------

Configuring caching options to use services backed by `doctrine/cache` is no
longer supported. Migrate to PSR-6 services instead.

The minimum required PHP version is now 8.4.

Support for the following major versions of the following packages has been dropped:

- `doctrine/dbal` 3
- `doctrine/persistence` 3
- `doctrine/orm` 2
- `psr/log` 1 and 2
- `twig/twig` 2

More details below

### Support for `doctrine/orm` 2 is dropped

This makes `DisconnectedMetadataFactory` redundant, as it relies on code
available only in `doctrine/orm` 2. It has been removed, as well as
`ClassMetadataConnection`.

Support for the YML and annotation metadata drivers has been dropped.

`Doctrine\Bundle\DoctrineBundle\Repository\LazyServiceEntityRepository` has
been removed without replacement.

Commands
--------

All command _classes_ in the `Doctrine\Bundle\DoctrineBundle\Command\Proxy`
namespace have been removed. Use the original commands provided by Doctrine
DBAL and ORM directly.

`doctrine:query:sql` has been removed. Use `dbal:run-sql` instead. All other
commands use the original command classes directly.

`doctrine:mapping:convert` and `doctrine:ensure-production-settings` have been
removed and do not have replacements.

`Doctrine\Bundle\DoctrineBundle\Command\ImportMappingCommand` has been removed
and does not have a replacement.

Configuration
-------------

### no-op configuration options removed

The following configuration options are no-ops when using `doctrine/orm` 3 and
have been removed:

- `doctrine.orm.entity_managers.some_em.report_fields_where_declared`
- `doctrine.orm.enable_lazy_ghost_objects`

Also, the 3 following options were no-ops when enabling native lazy objects
and have been removed as well:

- `doctrine.orm.auto_generate_proxy_classes`
- `doctrine.orm.proxy_dir`
- `doctrine.orm.proxy_namespace`

### The `doctrine.dbal.default_table_options.collate` default table option is removed

Use `doctrine.dbal.default_table_options.collation` instead.

### The `doctrine.dbal.override_url` option is removed

There is no replacement for this option.

### The `doctrine.dbal.types.some_custom_type.commented` option is removed

The `commented` option for custom types is no longer supported and has been
removed.

### The `doctrine.dbal.connection.some_connection.disable_type_comments` option is removed

The `disable_type_comments` option for connections is no longer supported and has
been removed.

### The `doctrine.dbal.connection.some_connection.platform_service` option is removed

The `platform_service` option for connections is no longer supported and has
been removed.

### The `doctrine.dbal.connection.some_connection.keep_slave` and `doctrine.dbal.connection.some_connection.slaves` option is removed

`doctrine.dbal.connection.some_connection.slaves` becomes
`doctrine.dbal.connection.some_connection.replicas`.

### The `doctrine.dbal.connection.some_connection.use_savepoints` option is removed

The `use_savepoints` option for connections is no longer supported and
has been removed.

### Controller resolver auto mapping can no longer be configured

The `doctrine.orm.controller_resolver.auto_mapping` option now only accepts `false` as value, to disallow the usage of the controller resolver auto mapping feature by default. The configuration option will be fully removed in 4.0.

Auto mapping used any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query. This method has been deprecated in Symfony 7.1 and is replaced with mapped route parameters.

If you were relying on this functionality, you will need to update your code to use explicit mapped route parameters instead.

### URL overrides are no longer allowed

When using the `doctrine.dbal.url` configuration option, you can no longer
specify other configuration options that would conflict with it, such as
`dbname`, `host`, etc.

### Service class parameters removed

The following service class parameters have been removed:

- `doctrine.class`
- `doctrine.data_collector.class`
- `doctrine.dbal.configuration.class`
- `doctrine.dbal.connection.event_manager.class`
- `doctrine.dbal.connection_factory.class`
- `doctrine.orm.configuration.class`
- `doctrine.orm.entity_listener_resolver.class`
- `doctrine.orm.entity_manager.class`
- `doctrine.orm.listeners.attach_entity_listeners.class`
- `doctrine.orm.listeners.resolve_target_entity.class`
- `doctrine.orm.manager_configurator.class`
- `doctrine.orm.metadata.attribute.class`
- `doctrine.orm.metadata.driver_chain.class`
- `doctrine.orm.metadata.php.class`
- `doctrine.orm.metadata.staticphp.class`
- `doctrine.orm.metadata.xml.class`
- `doctrine.orm.naming_strategy.default.class`
- `doctrine.orm.naming_strategy.underscore.class`
- `doctrine.orm.quote_strategy.ansi.class`
- `doctrine.orm.quote_strategy.default.class`
- `doctrine.orm.second_level_cache.cache_configuration.class`
- `doctrine.orm.second_level_cache.default_cache_factory.class`
- `doctrine.orm.second_level_cache.default_region.class`
- `doctrine.orm.second_level_cache.filelock_region.class`
- `doctrine.orm.second_level_cache.logger_chain.class`
- `doctrine.orm.second_level_cache.logger_statistics.class`
- `doctrine.orm.second_level_cache.regions_configuration.class`
- `doctrine.orm.security.user.provider.class`
- `doctrine.orm.typed_field_mapper.default.class`
- `doctrine.orm.validator.unique.class`
- `doctrine.orm.validator_initializer.class`
- `form.type_guesser.doctrine.class`

If your application was relying on these parameters, you should update your
service definitions to use the class names directly instead of parameter
references.

ConnectionFactory::createConnection() signature change
------------------------------------------------------

The signature of `ConnectionFactory::createConnection()` changed.
You should use stop passing an event manager argument.

```diff
- $connectionFactory->createConnection($params, $config, $eventManager, $mappingTypes)
+ $connectionFactory->createConnection($params, $config, $mappingTypes)
```

DependencyInjection namespace becomes internal
----------------------------------------------

The `Doctrine\Bundle\DoctrineBundle\DependencyInjection` namespace is now
considered internal, and all classes inside it are marked as final, except for
`Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineORMMappingsPass`.
Don't reference any classes from this namespace directly, except the one
mentioned earlier.

`Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventListenerInterface` has been removed
----------------------------------------------------------------------------------------

Use the `#[Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener]`
attribute instead.

`Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetsFilter` has been removed
----------------------------------------------------------------------------------

Implement your own include/exclude mechanism instead.

Type declarations
-----------------

Native type declarations have been added to all constants, properties, and
methods.

Twig filters
------------

The Twig filter `doctrine_pretty_query` has been removed.
