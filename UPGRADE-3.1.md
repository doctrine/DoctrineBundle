UPGRADE FROM 3.0 to 3.1
=======================

Configuration
-------------

The `doctrine.orm.controller_resolver.auto_mapping` only accepts `false`
as value since 3.0 and supplying the value explicitly is now marked as
deprecated.

The `doctrine.orm.entity_managers.some_em.enable_native_lazy_objects`
configuration option is deprecated and will be removed in DoctrineBundle 4.0,
as native lazy objects are now always enabled.
