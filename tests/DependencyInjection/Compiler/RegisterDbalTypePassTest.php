<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterDbalTypePass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\MoneyEntity;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\MoneyType as MoneyTypeFixture;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCaseAllPublicCompilerPass;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

use function array_filter;
use function array_values;
use function interface_exists;
use function md5;
use function method_exists;
use function set_exception_handler;
use function sprintf;
use function sys_get_temp_dir;

class RegisterDbalTypePassTest extends TestCase
{
    private static function requiresTypeRegistry(): void
    {
        if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test requires DBAL >= 4.5 with TypeRegistry injection support.');
    }

    private static function requiresNoTypeRegistry(): void
    {
        if (! method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test covers the fallback path for DBAL < 4.5 without TypeRegistry support.');
    }

    public function testNoTaggedTypesSkipsTypeRegistrySetup(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true);
        });

        foreach ($container->getDefinition('conf_conn1')->getMethodCalls() as [$method]) {
            self::assertNotSame('setTypeProvider', $method);
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

    public function testTypeServiceReceivesInjectedDependencies(): void
    {
        self::requiresTypeRegistry();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassTypeWithRequiredArg::class)
                ->addArgument('injected value')
                ->addTag('doctrine.dbal.type', ['type_name' => 'with_dependency']);

            $container->setAlias('registry_conn1', 'doctrine.dbal.conn1_connection.type_registry')->setPublic(true);
        });

        $type = $this->getRegistry($container, 'conn1')->get('with_dependency');

        self::assertInstanceOf(RegisterDbalTypePassTypeWithRequiredArg::class, $type);
        self::assertSame('injected value', $type->dependency);
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

        $setTypeProviderCalls = array_filter(
            $container->getDefinition('conf_conn1')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === 'setTypeProvider',
        );

        self::assertCount(1, $setTypeProviderCalls);
        $registryArg = array_values($setTypeProviderCalls)[0][1][0];

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
            $setTypeProviderCalls = array_filter(
                $container->getDefinition($alias)->getMethodCalls(),
                static fn (array $call): bool => $call[0] === 'setTypeProvider',
            );

            self::assertCount(1, $setTypeProviderCalls, sprintf('setTypeProvider not called on %s', $alias));
        }
    }

    public function testCustomTypeIsAvailableForOrmEntityMapping(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $exceptionHandler = set_exception_handler(null);

        $kernel = new RegisterDbalTypePassTestKernel();
        $kernel->boot();

        try {
            $em = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');

            $metadata = $em->getClassMetadata(MoneyEntity::class);
            self::assertSame('money', $metadata->fieldMappings['amount']['type']);

            if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
                /** @phpstan-ignore method.notFound (getTypeProvider() only exists on DBAL >= 4.5) */
                $typeRegistry = $em->getConnection()->getConfiguration()->getTypeProvider();
                self::assertInstanceOf(MoneyTypeFixture::class, $typeRegistry->get('money'));
            } else {
                self::assertTrue(Type::hasType('money'));
                self::assertInstanceOf(MoneyTypeFixture::class, Type::getType('money'));
            }
        } finally {
            $kernel->shutdown();
            // The kernel registers Symfony's debug exception handler on boot but does
            // not restore it on shutdown, so restore it here to avoid a risky test.
            set_exception_handler($exceptionHandler);
        }
    }

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

    public function testTaggedTypeDefinitionIsExcludedFromContainer(): void
    {
        self::requiresNoTypeRegistry();

        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $definition = $container->register(RegisterDbalTypePassBarType::class, RegisterDbalTypePassBarType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'bar']);

        (new RegisterDbalTypePass())->process($container);

        self::assertSame(
            [['source' => 'by tag "doctrine.dbal.type"']],
            $definition->getTag('container.excluded'),
        );
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

    /** @param class-string $typeClass */
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
        $registry = $container->get(sprintf('registry_%s', $connName));
        self::assertInstanceOf(TypeRegistry::class, $registry);

        return $registry;
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

// Doctrine\DBAL\Types\Type::__construct() is final before DBAL 4.5, so a type declaring
// its own constructor can only be defined when the TypeProvider API is available. The
// tests using this fixture are skipped on older DBAL versions.
if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
    class RegisterDbalTypePassTypeWithRequiredArg extends Type
    {
        public function __construct(public string $dependency)
        {
        }

        /** @param array<string, mixed> $column */
        public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
        {
            return 'VARCHAR(255)';
        }
    }
}

class RegisterDbalTypePassNotAType
{
}

class RegisterDbalTypePassTestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    /** @return iterable<Bundle> */
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new DoctrineBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'F00',
                'http_method_override' => false,
                'php_errors' => ['log' => true],
                'handle_all_throwables' => true,
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => ['driver' => 'pdo_sqlite'],
                'orm' => [
                    'mappings' => [
                        'Fixtures' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../Fixtures',
                            'prefix' => 'Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
            $container->register(MoneyTypeFixture::class)
                ->setAutoconfigured(true);
            $container->register('logger', NullLogger::class);
            $container->getCompilerPassConfig()->addPass(new TestCaseAllPublicCompilerPass());
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/sf_kernel_' . md5(static::class);
    }
}
