DBAL Types
==========

Custom DBAL types can be registered using the ``AsDbalType`` attribute. This
attribute allows you to define a name for your custom type directly in the class
definition. If the name is not provided, the class name will be used as the default.

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
registered with Doctrine.

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
