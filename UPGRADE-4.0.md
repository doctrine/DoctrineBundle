UPGRADE FROM 3 to 4
===================

Configuration
-------------

The `doctrine.orm.controller_resolver.auto_mapping` only accepted `false`
as value since 3.0 and is now removed.

The `doctrine.orm.entity_managers.some_em.enable_native_lazy_objects`
configuration option has been removed as native lazy objects are now always
enabled.

The XML configuration format is no longer supported. Configure the bundle with
YAML or PHP instead. The `doctrine-1.0.xsd` schema file has been removed.

Configuration keys are no longer normalized from their singular XML form in any
format. Use the plural keys, for example `connections`, `entity_managers`,
`types` and `filters`.
