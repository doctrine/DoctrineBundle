<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterDbalTypePass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

use function array_filter;
use function array_values;
use function method_exists;
use function sprintf;
use function sys_get_temp_dir;

class RegisterDbalTypePassTest extends TestCase
{
    private static function requiresTypeRegistry(): void
    {
        if (! method_exists(DbalConfiguration::class, 'setTypeRegistry')) {
            self::markTestSkipped('This test requires DBAL >= 4.5 with TypeRegistry injection support.');
        }
    }

    private static function requiresNoTypeRegistry(): void
    {
        if (method_exists(DbalConfiguration::class, 'setTypeRegistry')) {
            self::markTestSkipped('This test covers the fallback path for DBAL < 4.5 without TypeRegistry support.');
        }
    }

    // -----------------------------------------------------------------------
    // Tests for the TypeRegistry path (DBAL >= 4.5)
    // -----------------------------------------------------------------------

    public function testNoTaggedTypesSkipsTypeRegistrySetup(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true);
        });

        foreach ($container->getDefinition('conf_conn1')->getMethodCalls() as [$method]) {
            self::assertNotSame('setTypeRegistry', $method);
        }
    }

    public function testGlobalTypeIsRegisteredOnAllConnections(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type' => 'money']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            $container->setAlias('registry_conn2', 'doctrine.dbal.conn2_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeRegistered($container, 'conn2', 'money', RegisterDbalTypePassMoneyType::class);
    }

    public function testTypeRestrictedToOneConnection(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type' => 'money', 'connection' => 'conn1']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            $container->setAlias('registry_conn2', 'doctrine.dbal.conn2_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeNotRegistered($container, 'conn2', 'money');
    }

    public function testAutoconfiguredTypeRestrictedToConnectionViaAttribute(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyTypeForConn1::class)
                ->setAutoconfigured(true);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            $container->setAlias('registry_conn2', 'doctrine.dbal.conn2_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyTypeForConn1::class);
        $this->assertTypeNotRegistered($container, 'conn2', 'money');
    }

    public function testTypeRegisteredOnMultipleConnectionsViaRepeatedAttribute(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMultiConnectionType::class)
                ->setAutoconfigured(true);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            $container->setAlias('registry_conn2', 'doctrine.dbal.conn2_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'multi', RegisterDbalTypePassMultiConnectionType::class);
        $this->assertTypeNotRegistered($container, 'conn1', 'multi_alias');
        $this->assertTypeNotRegistered($container, 'conn2', 'multi');
        $this->assertTypeRegistered($container, 'conn2', 'multi_alias', RegisterDbalTypePassMultiConnectionType::class);
    }

    public function testTypeWithNoNameDefaultsToServiceId(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register(RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class)
                ->setAutoconfigured(true);

            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            $container->setAlias('registry_conn2', 'doctrine.dbal.conn2_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class);
        $this->assertTypeRegistered($container, 'conn2', RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class);
    }

    public function testTaggedTypeWithNoTypeAttributeDefaultsToServiceId(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type');

            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'my_type', RegisterDbalTypePassMoneyType::class);
    }

    public function testConfigTypesAreIncludedInRegistry(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(
            static function (ContainerBuilder $container): void {
                $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                    ->addTag('doctrine.dbal.type', ['type' => 'money']);

                $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
            },
            configTypes: ['uuid' => ['class' => RegisterDbalTypePassUuidType::class]],
        );

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeRegistered($container, 'conn1', 'uuid', RegisterDbalTypePassUuidType::class);
    }

    public function testTypeRegistryIsSetOnConfiguration(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type' => 'money']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
        });

        $setTypeRegistryCalls = array_filter(
            $container->getDefinition('conf_conn1')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === 'setTypeRegistry',
        );

        self::assertCount(1, $setTypeRegistryCalls);
        $registryArg = array_values($setTypeRegistryCalls)[0][1][0];

        if ($registryArg instanceof Reference) {
            $registryArg = $container->getDefinition((string) $registryArg);
        }

        self::assertInstanceOf(Definition::class, $registryArg);
        self::assertSame(TypeRegistry::class, $registryArg->getClass());
    }

    public function testTypeRegistryIsSetOnOrmConfiguration(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(
            static function (ContainerBuilder $container): void {
                $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                    ->addTag('doctrine.dbal.type', ['type' => 'money']);

                $container->setAlias('orm_conf_conn1', 'doctrine.orm.em_conn1_configuration')->setPublic(true);
                $container->setAlias('orm_conf_conn2', 'doctrine.orm.em_conn2_configuration')->setPublic(true);
            },
            withOrm: true,
        );

        foreach (['orm_conf_conn1', 'orm_conf_conn2'] as $alias) {
            $setTypeRegistryCalls = array_filter(
                $container->getDefinition($alias)->getMethodCalls(),
                static fn (array $call): bool => $call[0] === 'setTypeRegistry',
            );

            self::assertCount(1, $setTypeRegistryCalls, sprintf('setTypeRegistry not called on %s', $alias));
        }
    }

    // -----------------------------------------------------------------------
    // Tests for the fallback path (DBAL < 4.5, no TypeRegistry injection)
    // -----------------------------------------------------------------------

    public function testTaggedTypeAreAddedToConfig(): void
    {
        self::requiresNoTypeRegistry();

        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register(RegisterDbalTypePassBarType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'bar']);

        $container->compile();

        self::assertSame(['bar' => ['class' => RegisterDbalTypePassBarType::class]], $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testTypeMustBeASubclassOfTheDbalBaseType(): void
    {
        self::requiresNoTypeRegistry();

        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register(RegisterDbalTypePassNotAType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'invalid_type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "%s" class must extends "%s".', RegisterDbalTypePassNotAType::class, Type::class));

        $container->compile();
    }

    /** @param array<string, array{class: string}> $configTypes */
    private function createContainer(
        callable $func,
        array $configTypes = [],
        bool $withOrm = false,
    ): ContainerBuilder {
        $params = ['kernel.debug' => false];
        if ($withOrm) {
            $params['kernel.bundles']          = [];
            $params['kernel.bundles_metadata'] = [];
            $params['kernel.project_dir']      = sys_get_temp_dir();
            $params['kernel.environment']      = 'test';
            $params['kernel.build_dir']        = sys_get_temp_dir();
        }

        $container = new ContainerBuilder(new ParameterBag($params));

        $container->registerExtension(new DoctrineExtension());

        $doctrineConfig = [
            'dbal' => [
                'connections' => [
                    'conn1' => ['url' => 'mysql://user:pass@server1.tld:3306/db1'],
                    'conn2' => ['url' => 'mysql://user:pass@server2.tld:3306/db2'],
                ],
                'types' => $configTypes,
            ],
        ];

        if ($withOrm) {
            $doctrineConfig['orm'] = [
                'entity_managers' => [
                    'em_conn1' => ['connection' => 'conn1'],
                    'em_conn2' => ['connection' => 'conn2'],
                ],
            ];
        }

        $container->loadFromExtension('doctrine', $doctrineConfig);

        $container->addCompilerPass(new RegisterDbalTypePass());

        $func($container);

        $container->compile();

        return $container;
    }

    private function assertTypeRegistered(
        ContainerBuilder $container,
        string $connName,
        string $typeName,
        string $typeClass,
    ): void {
        $registry = $this->getRegistry($container, $connName);

        self::assertTrue($registry->has($typeName), sprintf(
            'Type "%s" not found in TypeRegistry for connection "%s".',
            $typeName,
            $connName,
        ));

        self::assertInstanceOf($typeClass, $registry->get($typeName));
    }

    private function assertTypeNotRegistered(ContainerBuilder $container, string $connName, string $typeName): void
    {
        $registryId = sprintf('doctrine.dbal.%s_connection.type_registry', $connName);

        if (! $container->hasDefinition($registryId)) {
            return;
        }

        $registry = $this->getRegistry($container, $connName);

        self::assertFalse($registry->has($typeName), sprintf(
            'Type "%s" should not be registered in TypeRegistry for connection "%s".',
            $typeName,
            $connName,
        ));
    }

    private function getRegistry(ContainerBuilder $container, string $connName): TypeRegistry
    {
        return $container->get(sprintf('registry_%s', $connName));
    }
}

class RegisterDbalTypePassMoneyType extends Type
{
    public function getSQLDeclaration(mixed $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(10,2)';
    }
}

class RegisterDbalTypePassUuidType extends Type
{
    public function getSQLDeclaration(mixed $column, AbstractPlatform $platform): string
    {
        return 'CHAR(36)';
    }
}

#[AsDbalType(name: 'money', connection: 'conn1')]
class RegisterDbalTypePassMoneyTypeForConn1 extends Type
{
    public function getSQLDeclaration(mixed $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(10,2)';
    }
}

#[AsDbalType(name: 'multi', connection: 'conn1')]
#[AsDbalType(name: 'multi_alias', connection: 'conn2')]
class RegisterDbalTypePassMultiConnectionType extends Type
{
    public function getSQLDeclaration(mixed $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }
}

#[AsDbalType]
class RegisterDbalTypePassAnonymousType extends Type
{
    public function getSQLDeclaration(mixed $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }
}

class RegisterDbalTypePassBarType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'bar';
    }
}

class RegisterDbalTypePassNotAType
{
}
