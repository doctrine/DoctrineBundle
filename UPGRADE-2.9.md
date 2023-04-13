UPGRADE FROM 2.8 to 2.9
=======================

Configuration
--------

### Schema manager factory

Due to changes and deprecations on `doctrine/dbal` 3.6+ we introduced a new option to configure a Schema manager factory.

On DBAL 3 the default factory is an instance of `Doctrine\DBAL\Schema\LegacySchemaManagerFactory`. 
For the upcoming DBAL 4 release the default will change to `Doctrine\DBAL\Schema\DefaultSchemaManagerFactory`.

To prepare for DBAL 4 and fix DBAL related deprecations we recommend changing the configuration to use the new factory.

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

### Deprecations

- the DBAL `platform_service` connection option is deprecated now. Use a driver middleware that would instantiate the platform instead.
