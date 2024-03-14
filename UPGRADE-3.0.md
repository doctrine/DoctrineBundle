UPGRADE FROM 2.x to 3.0
=======================

Configuration
-------------

### Controller resolver auto mapping default configuration changed

The default value of `doctrine.orm.controller_resolver.auto_mapping` has changed from `true` to `false`.

Auto mapping uses any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query.

If you were relying on this functionality, you will need to explicitly configure this now.

Types
-----

 * The `commented` configuration option for types is no longer supported and
 deprecated.
