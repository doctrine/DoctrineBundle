DBAL Types
==========

Custom DBAL types can be registered using the ``AsDbalType`` attribute. This
attribute allows you to define a name for your custom type directly in the class
definition. If the name is not provided, it defaults to the service id, which is
the fully-qualified class name of the type when using the attribute.

To register a custom DBAL type, create a class that extends
``Doctrine\DBAL\Types\Type`` and add the ``#[AsDbalType]`` attribute to it:

.. code-block:: php

    namespace App\Doctrine\Type;

    use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    use Doctrine\DBAL\Types\Type;

    #[AsDbalType(name: 'money')]
    class MoneyType extends Type
    {
        public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
        {
            return $platform->getDecimalTypeDeclarationSQL($column);
        }

        public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
        {
            return $value;
        }

        public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
        {
            return $value;
        }
    }

When using the ``AsDbalType`` attribute, the type will be automatically
registered. As the type is registered as a service, its constructor can declare
dependencies that are resolved by the container. This requires DBAL >= 4.5 and
ORM >= 3.7; on older versions the type is registered globally, without
dependency injection.

The attribute is autoconfigured to the ``doctrine.dbal.type`` tag, so it is
equivalent to tagging the service in the configuration. The ``type_name``
parameter defines the type name; when omitted, the service id is used:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Doctrine\Type\MoneyType:
            tags:
                - name: doctrine.dbal.type
                  type_name: money

By default the type is registered on every connection. To restrict it to a
single connection, set the ``connection`` on the attribute (which is repeatable,
so a type can be registered on several connections) or on the tag:

.. code-block:: php

    #[AsDbalType(name: 'money', connection: 'reporting')]
    class MoneyType extends Type
    {
        // ...
    }

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Doctrine\Type\MoneyType:
            tags:
                - name: doctrine.dbal.type
                  type_name: money
                  connection: reporting

Restricting a type to a connection requires DBAL >= 4.5 (and ORM >= 3.7 when the
ORM is used); on older versions the ``connection`` is ignored and the type is
registered globally.

Manual Registration
-------------------

Alternatively, you can register custom types in your configuration:

.. configuration-block::

    .. code-block:: yaml

        # config/packages/doctrine.yaml
        doctrine:
            dbal:
                types:
                    money: App\Doctrine\Type\MoneyType

    .. code-block:: xml

        <!-- config/packages/doctrine.xml -->
        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:doctrine="http://symfony.com/schema/dic/doctrine"
            xsi:schemaLocation="http://symfony.com/schema/dic/services
                http://symfony.com/schema/dic/services/services-1.0.xsd
                http://symfony.com/schema/dic/doctrine
                http://symfony.com/schema/dic/doctrine/doctrine-1.0.xsd">

            <doctrine:config>
                <doctrine:dbal>
                    <doctrine:type name="money">App\Doctrine\Type\MoneyType</doctrine:type>
                </doctrine:dbal>
            </doctrine:config>
        </container>

    .. code-block:: php

        // config/packages/doctrine.php
        use App\Doctrine\Type\MoneyType;
        use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

        return static function (ContainerConfigurator $containerConfigurator): void {
            $containerConfigurator->extension('doctrine', [
                'dbal' => [
                    'types' => [
                        'money' => MoneyType::class,
                    ],
                ],
            ]);
        };
