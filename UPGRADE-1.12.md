UPGRADE FROM 1.11 to 1.12
=========================

Deprecation of DoctrineCacheBundle
----------------------------------

With DoctrineCacheBundle [being deprecated](https://github.com/doctrine/DoctrineCacheBundle/issues/156),
configuring caches through it has been deprecated. If you are using anything
other than the `pool` or `id` cache types, please update your configuration to
either use symfony/cache through the `pool` type or configure your cache
services manually and use the `service` type.
