UPGRADE FROM 2.x to 3.0
=======================

Compatibility
-------------

Configuring caching options to use services backed by `doctrine/cache` is no
longer supported. Migrate to PSR-6 services instead.

Support for the following major versions of the following packages has been dropped:

- `doctrine/dbal` 3
- `doctrine/persistence` 3
- `doctrine/orm` 2
- `psr/log` 1 and 2
- `twig/twig` 2

More details below

### Support for `doctrine/orm` 2 is dropped

This makes `DisconnectedMetadataFactory` redundant, as it relies on code
available only in `doctrine/orm` 2. It has been removed.

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

### The `doctrine.dbal.default_table_options.collate` default table option is removed

Use `doctrine.dbal.default_table_options.collation` instead.

### Controller resolver auto mapping can no longer be configured

The `doctrine.orm.controller_resolver.auto_mapping` option now only accepts `false` as value, to disallow the usage of the controller resolver auto mapping feature by default. The configuration option will be fully removed in 4.0.

Auto mapping used any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query. This method has been deprecated in Symfony 7.1 and is replaced with mapped route parameters.

If you were relying on this functionality, you will need to update your code to use explicit mapped route parameters instead.

Types
-----

 * The `commented` configuration option for types is no longer supported and
 deprecated.
