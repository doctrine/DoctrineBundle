UPGRADE FROM 2.9 to 2.10
========================

Configuration
-------------

### Preparing for a new `report_fields_where_declared` mapping driver mode

Doctrine ORM 2.16+ makes a change to how the annotations and attribute mapping drivers report fields inherited from parent classes. For details, see https://github.com/doctrine/orm/pull/10455. It will trigger a deprecation notice unless the new mode is activated. In ORM 3.0, the new mode will be the only one.

The new mode ~does not~ should not make a difference for regular, valid use cases, but may lead to `MappingException`s for users with certain configurations that were not meant to be supported by the ORM in the first place. To avoid surprising users (even when their configuration is invalid) during a 2.16 _minor_ version upgrade, the transition to this new mode was implemented as an opt-in. This way, you can try and deal with the change any time you see fit.

In version 2.10+ of this bundle, a new configuration setting `report_fields_where_declared` was added at the entity manager configuration level. Set it to `true` to switch the mapping driver for the corresponding entity manager to the new mode. It is only relevant for mapping configurations using attributes or annotations.

Unless you set it to `true`, Doctrine ORM will emit deprecation messages mentioning this new setting.

### Preparing for the XSD validation for XML drivers

Doctrine ORM 2.14+ adds support for validating the XSD of XML mapping files. In ORM 3.0, this validation will be mandatory.

As the ecosystem is known to rely on custom elements in the XML mapping files that are forbidden when validating the XSD (for instance when using `gedmo/doctrine-extensions`), this validation is opt-in thanks to a `validate_xml_mapping` setting at the entity manager configuration level.

Unless you set it to `true`, Doctrine ORM will emit deprecation messages mentioning the XSD validation.

### Deprecations

- `Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface` has been deprecated. Use the `#[AsDoctrineListener]` attribute instead.
