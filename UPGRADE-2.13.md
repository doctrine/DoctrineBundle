UPGRADE FROM 2.12 to 2.13
========================

Configuration
-------------

### Controller resolver auto mapping deprecated

The controller resolver auto mapping functionality has been deprecated with Symfony 7.1, and is replaced with explicit mapped route parameters. Enabling the auto mapper by default using this bundle is now deprecated as well.

Auto mapping uses any route parameter that matches with a field name of the Entity to resolve as criteria in a find by query.

If you are relying on this functionality, you can update your code to use explicit mapped route parameters instead.
