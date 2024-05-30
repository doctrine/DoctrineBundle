UPGRADE FROM 2.x to 3.0
=======================

Configuration
-------------

### Controller resolver auto mapping can no longer be configured

The `doctrine.orm.controller_resolver.auto_mapping` option now only accepts `false` as value, to disallow the usage of the controller resolver auto mapping feature by default. The configuration option will be fully removed in 4.0.

Auto mapping used any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query. This method has been deprecated in Symfony 7.1 and is replaced with mapped route parameters.

If you were relying on this functionality, you will need to update your code to use explicit mapped route parameters instead.

Types
-----

 * The `commented` configuration option for types is no longer supported and
 deprecated.
