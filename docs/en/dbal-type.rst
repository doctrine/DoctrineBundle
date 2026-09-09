DBAL Types
==========

`Custom DBAL types`_ let you map a database column to a PHP value of your choice.
The recommended way to register one is the ``#[AsDbalType]`` attribute, which
declares the type directly on its class.

Registering a Type with the Attribute
-------------------------------------

Create a class that extends ``Doctrine\DBAL\Types\Type`` and add the
``#[AsDbalType]`` attribute to it:

.. code-block:: php

    // src/Doctrine/Type/MoneyType.php
    namespace App\Doctrine\Type;

    use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    use Doctrine\DBAL\Types\Type;

    #[AsDbalType]
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

The type is then available in your mappings. When no name is given, it defaults
to the fully-qualified class name, so you can reference the type by its class:

.. code-block:: php

    // src/Entity/Product.php
    namespace App\Entity;

    use App\Doctrine\Type\MoneyType;
    use Doctrine\ORM\Mapping as ORM;

    #[ORM\Entity]
    class Product
    {
        #[ORM\Column(type: MoneyType::class)]
        private Money $price;
    }

To use a shorter, explicit name instead, pass it to the attribute and reference
that name in the mapping:

.. code-block:: php

    #[AsDbalType(name: 'money')]
    class MoneyType extends Type
    {
        // ...
    }

.. code-block:: php

    #[ORM\Column(type: 'money')]
    private Money $price;

.. note::

    The ``#[AsDbalType]`` attribute and the ``doctrine.dbal.type`` tag require
    DoctrineBundle >= 3.3.

Dependency Injection
~~~~~~~~~~~~~~~~~~~~~

The type is registered as a service, so its constructor can declare dependencies
that are resolved by the container:

.. code-block:: php

    #[AsDbalType]
    class MoneyType extends Type
    {
        public function __construct(private readonly ExchangeRateProvider $rates)
        {
        }

        // ...
    }

.. note::

    Dependency injection requires DBAL >= 4.5 and ORM >= 3.7, where the type is
    resolved lazily from a per-connection registry. On
    older versions the type is registered in the global registry and instantiated
    without dependencies.

Restricting a Type to a Connection
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

By default the type is registered on every connection. Use the ``connection``
parameter to restrict it to a single connection. The attribute is repeatable, so
a type can be registered on several connections with different names:

.. code-block:: php

    #[AsDbalType(name: 'money', connection: 'default')]
    #[AsDbalType(name: 'money', connection: 'reporting')]
    class MoneyType extends Type
    {
        // ...
    }

.. note::

    Restricting a type to a connection requires DBAL >= 4.5 and ORM >= 3.7.
    On older versions the ``connection`` is ignored and the type is registered
    globally.

Registering a Type as a Service
-------------------------------

The attribute is autoconfigured to the ``doctrine.dbal.type`` tag, so tagging a
service is equivalent to using the attribute. This is useful when
autoconfiguration is disabled, or to register the same class several times with
different dependencies. The ``type_name`` attribute sets the type name; when
omitted, the service id is used:

.. configuration-block::

    .. code-block:: yaml

        # config/services.yaml
        services:
            App\Doctrine\Type\MoneyType:
                tags:
                    - { name: doctrine.dbal.type, type_name: money, connection: reporting }

    .. code-block:: php

        // config/services.php
        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        use App\Doctrine\Type\MoneyType;

        return static function (ContainerConfigurator $container): void {
            $container->services()
                ->set(MoneyType::class)
                ->tag('doctrine.dbal.type', ['type_name' => 'money', 'connection' => 'reporting']);
        };

Registering a Type with the Configuration
-----------------------------------------

When you do not need dependency injection, a type can be registered by mapping a
name to its class name under ``doctrine.dbal.types``. This is supported on all
DBAL versions and does not turn the type into a service:

.. configuration-block::

    .. code-block:: yaml

        # config/packages/doctrine.yaml
        doctrine:
            dbal:
                types:
                    money: App\Doctrine\Type\MoneyType

    .. code-block:: php

        // config/packages/doctrine.php
        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        use App\Doctrine\Type\MoneyType;

        return App::config([
            'doctrine' => [
                'dbal' => [
                    'types' => [
                        'money' => MoneyType::class,
                    ],
                ],
            ],
        ]);

.. _`Custom DBAL types`: https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/types.html#custom-mapping-types
