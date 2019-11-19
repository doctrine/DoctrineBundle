UPGRADE FROM 1.11 to 1.12
=========================

Deprecation of DoctrineCacheBundle
----------------------------------

With DoctrineCacheBundle [being deprecated](https://github.com/doctrine/DoctrineCacheBundle/issues/156),
configuring caches through it has been deprecated. If you are using anything
other than the `pool` or `id` cache types, please update your configuration to
either use symfony/cache through the `pool` type or configure your cache
services manually and use the `service` type.

Service aliases
---------------

 * Deprecated the `Symfony\Bridge\Doctrine\RegistryInterface` and `Doctrine\Bundle\DoctrineBundle\Registry` service alias, use `Doctrine\Common\Persistence\ManagerRegistry` instead.
 * Deprecated the `Doctrine\Common\Persistence\ObjectManager` service alias, use `Doctrine\ORM\EntityManagerInterface` instead.
