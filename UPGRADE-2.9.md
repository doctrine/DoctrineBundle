UPGRADE FROM 2.8 to 2.9
=======================

Configuration
--------

### Schema manager factory

Due to changes and deprecations on `doctrine/dbal` 3.6+ we introduced a new option to configure a Schema manager factory.

By default, it configures an instance of `Doctrine\DBAL\Schema\LegacySchemaManagerFactory` which is deprecated and won't work with the upcoming DBAL 4 anymore.

To fix this deprecation you need to use an instance of `Doctrine\DBAL\Schema\DefaultSchemaManagerFactory` (or possibly a custom implementation that suits your needs).

Before:
```yaml
doctrine:
    dbal:
        connections:
            default: ~
```

After:
```yaml
doctrine:
    dbal:
        connections:
            default:
                schema_manager_factory: doctrine.dbal.default_schema_manager_factory
```


