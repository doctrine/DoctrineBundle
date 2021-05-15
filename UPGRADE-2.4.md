UPGRADE FROM 2.3 to 2.4
=======================

Configuration
--------

 * The `override_url` configuration option has been deprecated.
 * Simplified configuration of DBAL connections when using single connection only has been deprecated. That means defining`doctrine.dbal.connections` explicitly is required now. Instead of using
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
 * Simplified configuration of ORM entity managers when using single entity manager only has been deprecated. That means defining`doctrine.orm.entity_managers` explicitly is required now. Instead of using
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
