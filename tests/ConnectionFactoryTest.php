<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\Deprecations\Deprecation;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Symfony\Component\DependencyInjection\ServiceLocator;

use function assert;
use function method_exists;

class ConnectionFactoryTest extends TestCase
{
    use VerifyDeprecations;

    private Configuration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = (new Configuration())->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
    }

    public function testDefaultCharsetNonMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $params  = [
            'driverClass' => Driver\PDO\SQLite\Driver::class,
            'wrapperClass' => FakeConnection::class,
        ];

        $creationCount = FakeConnection::$creationCount;
        $connection    = $factory->createConnection($params, $this->configuration);

        $this->assertInstanceof(FakeConnection::class, $connection);
        /** @var array{charset: string} $params */
        $params = $connection->getParams();
        $this->assertSame('utf8', $params['charset']);
        $this->assertSame(1 + $creationCount, FakeConnection::$creationCount);
    }

    public function testDefaultCharsetMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $params  = ['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'];

        $connection = $factory->createConnection($params, $this->configuration);

        $params = $connection->getParams();
        /** @var array{charset: string} $params */
        $this->assertSame('utf8mb4', $params['charset']);
    }

    public function testDefaultCollationMySql(): void
    {
        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection(['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'], $this->configuration);

        $params = $connection->getParams();
        /** @var array{defaultTableOptions: array{collation: string}} $params */
        $defaultTableOptions = $params['defaultTableOptions'];
        $this->assertSame(
            'utf8mb4_unicode_ci',
            $defaultTableOptions['collation'],
        );
    }

    public function testConnectionCharsetFromUrl(): void
    {
        /** @psalm-suppress InvalidArgument Need to be compatible with DBAL < 4, which still has `$params['url']` */
        $connection = (new ConnectionFactory([]))->createConnection(
            ['url' => 'mysql://root:password@database:3306/main?charset=utf8mb4_unicode_ci'],
            $this->configuration,
        );

        $params = $connection->getParams();
        /** @var array{charset: string} $params */
        $this->assertEquals('utf8mb4_unicode_ci', $params['charset']);
    }

    public function testDbnameSuffix(): void
    {
        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
                'url' => 'mysql://root:password@database:3306/main?serverVersion=mariadb-12.1.1',
                'dbname_suffix' => '_test',
            ],
            $this->configuration,
        );

        $params = $connection->getParams();
        /** @var array{dbname: string} $params */
        $this->assertSame('main_test', $params['dbname']);
    }

    public function testDbnameSuffixWithCharsetDoesNotResolveThePlatformWithoutServerVersion(): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6343');

        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
                'url' => 'mysql://root:password@database:3306/main?charset=utf8mb4',
                'dbname_suffix' => '_test',
            ],
            $this->configuration,
        );

        $params = $connection->getParams();
        assert(isset($params['dbname']));
        assert(isset($params['charset']));

        $this->assertSame('main_test', $params['dbname']);
        $this->assertSame('utf8mb4', $params['charset']);
    }

    public function testDbnameSuffixForReplicas(): void
    {
        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
                'driver' => 'pdo_mysql',
                'serverVersion' => '9.4.0',
                'primary' => [
                    'url' => 'mysql://root:password@database:3306/primary?serverVersion=mariadb-12.1.1',
                    'dbname_suffix' => '_test',
                ],
                'replica' => [
                    'replica1' => [
                        'url' => 'mysql://root:password@database:3306/replica?serverVersion=mariadb-12.1.1',
                        'dbname_suffix' => '_test',
                    ],
                ],
            ],
            $this->configuration,
        );

        /** @var array{primary: array{dbname: string}, replica: array{replica1: array{dbname: string}}} $parsedParams */
        $parsedParams = $connection->getParams();
        $this->assertArrayHasKey('primary', $parsedParams);
        $this->assertArrayHasKey('replica', $parsedParams);
        $this->assertArrayHasKey('replica1', $parsedParams['replica']);

        $this->assertSame('primary_test', $parsedParams['primary']['dbname']);
        $this->assertSame('replica_test', $parsedParams['replica']['replica1']['dbname']);
    }

    public function testServiceTypesAreRegisteredAsInstances(): void
    {
        self::requiresTypeProvider();

        $type    = new ConnectionFactoryGlobalType('injected value');
        $locator = new ServiceLocator(['global_type' => static fn (): Type => $type]);

        (new ConnectionFactory($locator))->createConnection(
            ['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'],
            $this->configuration,
        );

        $registry = (new Configuration())->getTypeProvider();
        self::assertInstanceOf(TypeRegistry::class, $registry);

        // The instance built by the container is registered, so it keeps its injected dependency.
        self::assertTrue($registry->has('global_type'));
        self::assertSame($type, $registry->get('global_type'));
        self::assertSame('injected value', $type->dependency);
    }

    public function testServiceTypeUsedForSeveralNamesIsCopied(): void
    {
        self::requiresTypeProvider();

        $type    = new ConnectionFactoryGlobalType('injected value');
        $locator = new ServiceLocator([
            'global_type_one' => static fn (): Type => $type,
            'global_type_two' => static fn (): Type => $type,
        ]);

        (new ConnectionFactory($locator))->createConnection(
            ['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'],
            $this->configuration,
        );

        $registry = (new Configuration())->getTypeProvider();
        self::assertInstanceOf(TypeRegistry::class, $registry);

        // The global registry refuses the same instance under two names, so the extra name gets a copy.
        self::assertSame($type, $registry->get('global_type_one'));
        self::assertNotSame($type, $registry->get('global_type_two'));
        self::assertInstanceOf(ConnectionFactoryGlobalType::class, $registry->get('global_type_two'));
        self::assertSame('injected value', $registry->get('global_type_two')->dependency);
    }

    public function testServiceTypesAreRegisteredWithoutDeprecation(): void
    {
        self::requiresTypeProvider();

        $triggered = Deprecation::getTriggeredDeprecations();

        $locator = new ServiceLocator(['global_type' => static fn (): Type => new ConnectionFactoryConfigType()]);
        (new ConnectionFactory($locator))
            ->createConnection(['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'], $this->configuration);

        // The static methods of Type are deprecated on DBAL 4.6, and they trigger the deprecation
        // whatever the error reporting configuration. The counter of doctrine/deprecations is read
        // instead of relying on failOnDeprecation, which ignores the deprecations of the
        // dependencies.
        self::assertSame($triggered, Deprecation::getTriggeredDeprecations(), 'Registering a type must not trigger a deprecation.');

        $registry = (new Configuration())->getTypeProvider();
        self::assertInstanceOf(TypeRegistry::class, $registry);
        self::assertInstanceOf(ConnectionFactoryConfigType::class, $registry->get('global_type'));
    }

    public function testConfigTypesAreRegisteredWithTheStaticMethodsOfType(): void
    {
        self::requiresNoTypeProvider();

        (new ConnectionFactory(['global_config_type' => ['class' => ConnectionFactoryConfigType::class]]))
            ->createConnection(['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'], $this->configuration);

        // The container passes the doctrine.dbal.types option as an array when the types cannot be
        // services, so the factory registers them with the static methods of Type.
        self::assertTrue(Type::hasType('global_config_type'));
        self::assertInstanceOf(ConnectionFactoryConfigType::class, Type::getType('global_config_type'));
    }

    private static function requiresTypeProvider(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (method_exists(Configuration::class, 'getTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test requires DBAL >= 4.6.');
    }

    private static function requiresNoTypeProvider(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (! method_exists(Configuration::class, 'getTypeProvider')) {
            return;
        }

        self::markTestSkipped('This test covers DBAL < 4.6, without type provider support.');
    }
}

// The constructor of Doctrine\DBAL\Types\Type is final before DBAL 4.6, so a type declaring its
// own constructor can only be defined when the TypeProvider API is available.
/** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
if (method_exists(Configuration::class, 'getTypeProvider')) {
    class ConnectionFactoryGlobalType extends Type
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

class ConnectionFactoryConfigType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }
}

class FakeConnection extends Connection
{
    public static int $creationCount = 0;

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $params
     */
    public function __construct(array $params, Driver $driver, Configuration|null $config = null)
    {
        ++self::$creationCount;

        parent::__construct($params, $driver, $config);
    }
}
