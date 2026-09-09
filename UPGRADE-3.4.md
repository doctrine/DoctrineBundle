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
