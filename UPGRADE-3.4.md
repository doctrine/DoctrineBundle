UPGRADE FROM 3.3 to 3.4
=======================

ServiceEntityRepository
-----------------------

Passing `null` as `$lockMode` to `ServiceEntityRepository::find()` is deprecated
and will not be possible in DoctrineBundle 4.0. Omit the argument or pass
`LockMode::NONE` instead.

```diff
-$repository->find($id, null);
+$repository->find($id);
```

DBAL types
----------

With DBAL 4.6, the types declared with the `#[AsDbalType]` attribute or the
`doctrine.dbal.type` tag are registered on each connection instead of the global
type registry. `Type::getType()` and `Type::hasType()` do not return them
anymore. The types that other code registers with `Type::addType()` stay
available, because the type provider of a connection falls back to the global
registry.

The types are created by the dependency injection container, so their
constructor can receive services. The `#[AsDbalType]` attribute is repeatable
and accepts a `connection` argument to restrict a type to a single connection.

Resolving those types in a query, in persistence or in schema validation
requires Doctrine ORM 3.8, which is the first version that reads the type
provider of the connection. This bundle now requires it. With an older ORM
version, keep declaring the types with the `doctrine.dbal.types` configuration
option, which registers them in the global registry.

With DBAL < 4.6, the types keep being registered in the global registry, without
dependency injection and without per-connection restriction. A type that
declares a constructor with required parameters fails when a connection is
created.

Mapping alias
-------------

The `alias` option of a mapping is deprecated and will be removed in
DoctrineBundle 4.0. It has no effect: Doctrine ORM 3 no longer supports entity
namespace aliases. Remove it from your configuration.

```diff
 doctrine:
     orm:
         mappings:
             App:
                 type: attribute
                 dir: '%kernel.project_dir%/src/Entity'
                 prefix: 'App\Entity'
-                alias: App
```
