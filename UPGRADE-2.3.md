UPGRADE FROM 2.2 to 2.3
=======================

Configuration
--------
* Not setting `doctrine.dbal.override_url` to `true` when using a `url` parameter in a connection is deprecated.

Commands
--------

 * The `\Doctrine\Bundle\DoctrineBundle\Command\Proxy\ClearMetadataCacheDoctrineCommand` (`doctrine:cache:clear-metadata`) is deprecated, metadata cache now uses PHP Array cache which can not be cleared.

Configuration
--------
 * The `metadata_cache_driver` configuration key has been deprecated. PHP Array cache is now automatically registered when `%kernel.debug%` is false.

DependencyInjection
--------

 * `\Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetFilter` has been deprecated. Implement your own include/exclude strategies.
 * `\Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\WellKnownSchemaFilterPass` has been deprecated. Implement your own include/exclude strategies.
