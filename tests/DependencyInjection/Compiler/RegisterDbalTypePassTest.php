<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\Bundle\DoctrineBundle\Dbal\TypeProviderGlobalFallback;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterDbalTypePass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\MoneyEntity;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\MoneyType as MoneyTypeFixture;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCaseAllPublicCompilerPass;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterDatePointTypePass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

use function array_column;
use function array_filter;
use function array_values;
use function class_exists;
use function interface_exists;
use function md5;
use function method_exists;
use function set_exception_handler;
use function sprintf;
use function sys_get_temp_dir;

class RegisterDbalTypePassTest extends TestCase
{
    private static function requiresTypeProvider(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test requires DBAL >= 4.6 with type provider support.');
    }

    private static function requiresNoTypeProvider(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (! method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test covers the fallback path for DBAL < 4.6 without type provider support.');
    }

    public function testNoTaggedTypesSkipsTypeProviderSetup(): void
    {
        self::requiresTypeProvider();

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
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'money']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            $container->setAlias('provider_conn2', 'doctrine.dbal.conn2_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeRegistered($container, 'conn2', 'money', RegisterDbalTypePassMoneyType::class);
    }

    public function testTypeServiceReceivesInjectedDependencies(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassTypeWithRequiredArg::class)
                ->addArgument('injected value')
                ->addTag('doctrine.dbal.type', ['type_name' => 'with_dependency']);

            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
        });

        $type = $this->getTypeProvider($container, 'conn1')->get('with_dependency');

        self::assertInstanceOf(RegisterDbalTypePassTypeWithRequiredArg::class, $type);
        self::assertSame('injected value', $type->dependency);
    }

    public function testTypeRestrictedToOneConnection(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'money', 'connection' => 'conn1']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            $container->setAlias('provider_conn2', 'doctrine.dbal.conn2_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeNotRegistered($container, 'conn2', 'money');
    }

    public function testAutoconfiguredTypeRestrictedToConnectionViaAttribute(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyTypeForConn1::class)
                ->setAutoconfigured(true);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            $container->setAlias('provider_conn2', 'doctrine.dbal.conn2_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyTypeForConn1::class);
        $this->assertTypeNotRegistered($container, 'conn2', 'money');
    }

    public function testTypeRegisteredOnMultipleConnectionsViaRepeatedAttribute(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMultiConnectionType::class)
                ->setAutoconfigured(true);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
            $container->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')->setPublic(true);
            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            $container->setAlias('provider_conn2', 'doctrine.dbal.conn2_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'multi', RegisterDbalTypePassMultiConnectionType::class);
        $this->assertTypeNotRegistered($container, 'conn1', 'multi_alias');
        $this->assertTypeNotRegistered($container, 'conn2', 'multi');
        $this->assertTypeRegistered($container, 'conn2', 'multi_alias', RegisterDbalTypePassMultiConnectionType::class);
    }

    public function testTypeWithNoNameDefaultsToServiceId(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register(RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class)
                ->setAutoconfigured(true);

            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            $container->setAlias('provider_conn2', 'doctrine.dbal.conn2_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class);
        $this->assertTypeRegistered($container, 'conn2', RegisterDbalTypePassAnonymousType::class, RegisterDbalTypePassAnonymousType::class);
    }

    public function testTaggedTypeWithNoTypeAttributeDefaultsToServiceId(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type');

            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
        });

        $this->assertTypeRegistered($container, 'conn1', 'my_type', RegisterDbalTypePassMoneyType::class);
    }

    public function testConfigTypesAreIncludedInRegistry(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(
            static function (ContainerBuilder $container): void {
                $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                    ->addTag('doctrine.dbal.type', ['type_name' => 'money']);

                $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
            },
            configTypes: ['uuid' => ['class' => RegisterDbalTypePassUuidType::class]],
        );

        $this->assertTypeRegistered($container, 'conn1', 'money', RegisterDbalTypePassMoneyType::class);
        $this->assertTypeRegistered($container, 'conn1', 'uuid', RegisterDbalTypePassUuidType::class);
    }

    public function testTypeProviderIsSetOnConfiguration(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'money']);

            $container->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')->setPublic(true);
        });

        $setTypeProviderCalls = array_filter(
            $container->getDefinition('conf_conn1')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === 'setTypeProvider',
        );

        self::assertCount(1, $setTypeProviderCalls);
        $providerArg = array_values($setTypeProviderCalls)[0][1][0];

        if ($providerArg instanceof Reference) {
            $providerArg = $container->getDefinition((string) $providerArg);
        }

        self::assertInstanceOf(Definition::class, $providerArg);
        self::assertSame(TypeProviderGlobalFallback::class, $providerArg->getClass());
    }

    public function testGloballyRegisteredTypesStayAvailable(): void
    {
        self::requiresTypeProvider();

        // A default Configuration returns the global type registry, which is the registry that the
        // connection provider falls back to.
        $globalProvider = (new DbalConfiguration())->getTypeProvider();
        self::assertInstanceOf(TypeRegistry::class, $globalProvider);
        $globalProvider->register('global_fallback_type', RegisterDbalTypePassBarType::class);

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'money']);

            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
        });

        $provider = $this->getTypeProvider($container, 'conn1');

        // Types registered with Type::addType() by other code are still resolved.
        self::assertTrue($provider->has('global_fallback_type'));
        self::assertInstanceOf(RegisterDbalTypePassBarType::class, $provider->get('global_fallback_type'));

        // Built-in types are provided too.
        self::assertTrue($provider->has('integer'));

        // The types of the connection take precedence.
        self::assertInstanceOf(RegisterDbalTypePassMoneyType::class, $provider->get('money'));
    }

    public function testHasDoesNotTriggerDeprecationForUnknownType(): void
    {
        self::requiresTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('my_type', RegisterDbalTypePassMoneyType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'money']);

            $container->setAlias('provider_conn1', 'doctrine.dbal.conn1_connection.type_provider')->setPublic(true);
        });

        $provider = $this->getTypeProvider($container, 'conn1');

        // The test fails if the lookup triggers a deprecation, because failOnDeprecation is enabled.
        self::assertFalse($provider->has('unknown_type'));
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

            // getTypeProvider() only exists on DBAL >= 4.6.
            /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
            if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
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

    public function testCustomTypeIsUsableForOrmPersistence(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $exceptionHandler = set_exception_handler(null);

        $kernel = new RegisterDbalTypePassTestKernel();
        $kernel->boot();

        try {
            $em = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');

            (new SchemaTool($em))->createSchema([$em->getClassMetadata(MoneyEntity::class)]);

            $entity         = new MoneyEntity();
            $entity->amount = '12.34';
            $em->persist($entity);
            $em->flush();
            $em->clear();

            // The ORM resolves the type of the column through the provider of the connection,
            // when it writes the entity and when it reads it back.
            $found = $em->find(MoneyEntity::class, 1);
            self::assertInstanceOf(MoneyEntity::class, $found);
            self::assertEquals('12.34', $found->amount);
        } finally {
            $kernel->shutdown();
            set_exception_handler($exceptionHandler);
        }
    }

    public function testTypesAddedByOtherPassesAreCollected(): void
    {
        if (! class_exists(RegisterDatePointTypePass::class)) {
            self::markTestSkipped('This test requires the Doctrine bridge.');
        }

        $container = new ContainerBuilder();
        (new DoctrineBundle())->build($container);

        // A pass added by another bundle with the default priority, after the bundle is built.
        $otherPass = new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
            }
        };
        $container->addCompilerPass($otherPass);

        $order = [];
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass === $otherPass) {
                $order[] = 'other pass';
            } elseif ($pass instanceof RegisterDatePointTypePass || $pass instanceof RegisterDbalTypePass) {
                $order[] = $pass::class;
            }
        }

        // The pass of the bridge adds date_point, day_point and time_point to the types of the
        // connection factory, so it must run before the types are collected for the connections.
        // The negative priority of the pass also keeps it after the passes that other bundles add.
        self::assertSame([RegisterDatePointTypePass::class, 'other pass', RegisterDbalTypePass::class], $order);
    }

    public function testDatePointTypeIsAvailableOnTheConnection(): void
    {
        if (! class_exists(RegisterDatePointTypePass::class)) {
            self::markTestSkipped('This test requires the Doctrine bridge.');
        }

        if (! class_exists(DatePoint::class)) {
            self::markTestSkipped('This test requires the Clock component.');
        }

        self::requiresTypeProvider();

        $exceptionHandler = set_exception_handler(null);

        $kernel = new RegisterDbalTypePassTestKernel();
        $kernel->boot();

        try {
            $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
            self::assertInstanceOf(Connection::class, $connection);

            // The bridge adds date_point to the types of the connection factory, and the pass
            // collects those types for the provider of each connection.
            self::assertTrue($connection->getConfiguration()->getTypeProvider()->has('date_point'));

            // The connection resolves the type through its provider.
            $date = new DatePoint('2026-10-14 12:34:56', new DateTimeZone('UTC'));
            self::assertSame('2026-10-14 12:34:56', $connection->convertToDatabaseValue($date, 'date_point'));

            $phpValue = $connection->convertToPHPValue('2026-10-14 12:34:56', 'date_point');
            self::assertInstanceOf(DatePoint::class, $phpValue);
            self::assertSame('2026-10-14 12:34:56', $phpValue->format('Y-m-d H:i:s'));
        } finally {
            $kernel->shutdown();
            set_exception_handler($exceptionHandler);
        }
    }

    public function testTaggedTypeAreAddedToConfig(): void
    {
        self::requiresNoTypeProvider();

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
        self::requiresNoTypeProvider();

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
        self::requiresNoTypeProvider();

        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register(RegisterDbalTypePassNotAType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'invalid_type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "%s" class must extends "%s".', RegisterDbalTypePassNotAType::class, Type::class));

        $container->compile();
    }

    public function testTypeClassesArePreloaded(): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->setAlias('connection_factory', 'doctrine.dbal.connection_factory')->setPublic(true);
        }, ['money' => ['class' => RegisterDbalTypePassMoneyType::class]]);

        $preloaded = array_column($container->getDefinition('connection_factory')->getTag('container.preload'), 'class');

        self::assertContains(BigIntType::class, $preloaded);
        self::assertContains(RegisterDbalTypePassMoneyType::class, $preloaded);
    }

    public function testTaggedTypeClassesArePreloaded(): void
    {
        self::requiresNoTypeProvider();

        $container = $this->createContainer(static function (ContainerBuilder $container): void {
            $container->register('bar_type', RegisterDbalTypePassBarType::class)
                ->addTag('doctrine.dbal.type', ['type_name' => 'bar']);

            $container->setAlias('connection_factory', 'doctrine.dbal.connection_factory')->setPublic(true);
        });

        $preloaded = array_column($container->getDefinition('connection_factory')->getTag('container.preload'), 'class');

        self::assertContains(RegisterDbalTypePassBarType::class, $preloaded);
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
        $provider = $this->getTypeProvider($container, $connName);

        self::assertTrue($provider->has($typeName), sprintf(
            'Type "%s" not found in the type provider of connection "%s".',
            $typeName,
            $connName,
        ));

        self::assertInstanceOf($typeClass, $provider->get($typeName));
    }

    private function assertTypeNotRegistered(ContainerBuilder $container, string $connName, string $typeName): void
    {
        $providerId = sprintf('doctrine.dbal.%s_connection.type_provider', $connName);

        if (! $container->hasDefinition($providerId)) {
            return;
        }

        $provider = $this->getTypeProvider($container, $connName);

        self::assertFalse($provider->has($typeName), sprintf(
            'Type "%s" should not be registered in the type provider of connection "%s".',
            $typeName,
            $connName,
        ));
    }

    private function getTypeProvider(ContainerBuilder $container, string $connName): TypeProvider
    {
        $provider = $container->get(sprintf('provider_%s', $connName));
        self::assertInstanceOf(TypeProviderGlobalFallback::class, $provider);

        return $provider;
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

// Doctrine\DBAL\Types\Type::__construct() is final before DBAL 4.6, so a type declaring
// its own constructor can only be defined when the TypeProvider API is available. The
// tests using this fixture are skipped on older DBAL versions.
/** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
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
