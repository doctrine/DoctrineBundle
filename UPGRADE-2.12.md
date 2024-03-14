UPGRADE FROM 2.11 to 2.12
========================

Configuration
-------------

### Controller resolver auto mapping default configuration will be changed

The default value of `doctrine.orm.controller_resolver.auto_mapping` will be changed from `true` to `false` in 3.0.

Auto mapping uses any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query.

If you are relying on this functionality, you will need to configure it explicitly to silence the deprecation notice.
