UPGRADE FROM 2.3 to 2.4
=======================

Configuration
--------

 * The `override_url` configuration option has been deprecated.
 * Combined use of simplified connection configuration in DBAL (without `connections` key) 
   and multiple connection configuration is disallowed now. If you experience this issue, instead of
```yaml
doctrine:
    dbal:
      url: '%env(DATABASE_URL)%'
```
use
```yaml
doctrine:
  dbal:
    connections:
      default:
        url: '%env(DATABASE_URL)%'        
```
* Combined use of simplified entity manager configuration in ORM (without `entity_managers` key)
  and multiple entity managers configuration is disallowed now. If you experience this issue, instead of
```yaml
doctrine:
  orm:
    mappings:
```
use
```yaml
doctrine:
  orm:
    entity_managers:
      default:
        mappings:        
```

ConnectionFactory
--------

 * The `connection_override_options` parameter has been deprecated.
