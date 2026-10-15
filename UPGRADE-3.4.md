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
requires an ORM that reads the type provider of the connection, which is the
case from Doctrine ORM 3.8. With an older ORM, which resolves types through the
global registry, the bundle registers the tagged types there instead, as the
instances built by the container, when the first connection is created. The
connections do not receive a type provider in that case, so each type has a
single registration. Two limitations come with the global registry, which is
shared by every connection:

- the `connection` argument is ignored, so the type is visible on all
  connections;
- two services cannot declare the same type name, which is reported at compile
  time.

The `doctrine.dbal.types` configuration option keeps registering types in the
global registry with DBAL, without dependency injection.

With DBAL < 4.6, the types keep being registered in the global registry, without
dependency injection and without per-connection restriction. A type that
declares a constructor with required parameters fails when a connection is
created.
