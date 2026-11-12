UPGRADE FROM 3 to 4
===================

Configuration
-------------

The `doctrine.orm.controller_resolver.auto_mapping` only accepted `false`
as value since 3.0 and is now removed.

The `doctrine.orm.entity_managers.some_em.enable_native_lazy_objects`
configuration option has been removed as native lazy objects are now always
enabled.
