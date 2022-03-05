<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Exception as InternalDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Exception;

use function array_intersect_key;
use function class_exists;
use function defined;

// Compatibility with DBAL < 3
class_exists(\Doctrine\DBAL\Platforms\MySqlPlatform::class);

class ConnectionFactoryTest extends TestCase
{
    public function testContainer(): void
    {
        $typesConfig  = [];
        $factory      = new ConnectionFactory($typesConfig);
        $params       = ['driverClass' => FakeDriver::class];
        $config       = null;
        $eventManager = null;
        $mappingTypes = ['' => ''];
        /** @psalm-suppress InvalidArgument */
        $exception = class_exists(Driver\AbstractDriverException::class) ?
            new DriverException('', $this->createMock(Driver\AbstractDriverException::class)) :
            new DriverException($this->createMock(InternalDriverException::class), null);

        // put the mock into the fake driver
        FakeDriver::$exception = $exception;

        $this->expectException(DBALException::class);

        try {
            $factory->createConnection($params, $config, $eventManager, $mappingTypes);
        } finally {
            FakeDriver::$exception = null;
        }
    }

    public function testDefaultCharsetNonMySql(): void
    {
        FakeDriver::$platform = new SqlitePlatform();
        $factory              = new ConnectionFactory([]);
        $params               = [
            'driverClass' => FakeDriver::class,
            'wrapperClass' => FakeConnection::class,
        ];

        $creationCount = FakeConnection::$creationCount;
        $connection    = $factory->createConnection($params);

        $this->assertInstanceof(FakeConnection::class, $connection);
        $this->assertSame('utf8', $connection->getParams()['charset']);
        $this->assertSame(1 + $creationCount, FakeConnection::$creationCount);
    }

    public function testDefaultCharsetMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $params  = ['driver' => 'pdo_mysql'];

        $connection = $factory->createConnection($params);

        $this->assertSame('utf8mb4', $connection->getParams()['charset']);
    }

    public function testDefaultCollateMySql(): void
    {
        if (defined('Doctrine\DBAL\Connection::PARAM_ASCII_STR_ARRAY')) {
            self::markTestSkipped('This test is only relevant for DBAL < 3.3');
        }

        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection(['driver' => 'pdo_mysql']);

        $this->assertSame(
            'utf8mb4_unicode_ci',
            $connection->getParams()['defaultTableOptions']['collate']
        );
    }

    public function testDefaultCollationMySql(): void
    {
        if (! defined('Doctrine\DBAL\Connection::PARAM_ASCII_STR_ARRAY')) {
            self::markTestSkipped('This test is only relevant for DBAL >= 3.3');
        }

        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection(['driver' => 'pdo_mysql']);

        $this->assertSame(
            'utf8mb4_unicode_ci',
            $connection->getParams()['defaultTableOptions']['collation']
        );
    }

    public function testCollateMapsToCollationForMySql(): void
    {
        if (! defined('Doctrine\DBAL\Connection::PARAM_ASCII_STR_ARRAY')) {
            self::markTestSkipped('This test is only relevant for DBAL >= 3.3');
        }

        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection([
            'driver' => 'pdo_mysql',
            'defaultTableOptions' => ['collate' => 'my_collation'],
        ]);

        $tableOptions = $connection->getParams()['defaultTableOptions'];
        $this->assertArrayNotHasKey('collate', $tableOptions);
        $this->assertSame(
            'my_collation',
            $tableOptions['collation']
        );
    }

    /** @group legacy */
    public function testConnectionOverrideOptions(): void
    {
        $params = [
            'dbname' => 'main_test',
            'host' => 'db_test',
            'port' => 5432,
            'user' => 'tester',
            'password' => 'wordpass',
        ];

        $connection = (new ConnectionFactory([]))->createConnection([
            'url' => 'mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8',
            'connection_override_options' => $params,
        ]);

        $this->assertEquals($params, array_intersect_key($connection->getParams(), $params));
    }

    public function testConnectionCharsetFromUrl()
    {
        $connection = (new ConnectionFactory([]))->createConnection(['url' => 'mysql://root:password@database:3306/main?charset=utf8mb4_unicode_ci']);

        $this->assertEquals('utf8mb4_unicode_ci', $connection->getParams()['charset']);
    }

    public function testDbnameSuffix(): void
    {
        $connection = (new ConnectionFactory([]))->createConnection([
            'url' => 'mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8',
            'dbname_suffix' => '_test',
        ]);

        $this->assertSame('main_test', $connection->getParams()['dbname']);
    }

    public function testDbnameSuffixForReplicas(): void
    {
        $connection = (new ConnectionFactory([]))->createConnection([
            'driver' => 'pdo_mysql',
            'primary' => [
                'url' => 'mysql://root:password@database:3306/primary?serverVersion=mariadb-10.5.8',
                'dbname_suffix' => '_test',
            ],
            'replica' => [
                'replica1' => [
                    'url' => 'mysql://root:password@database:3306/replica?serverVersion=mariadb-10.5.8',
                    'dbname_suffix' => '_test',
                ],
            ],
        ]);

        $parsedParams = $connection->getParams();
        $this->assertArrayHasKey('primary', $parsedParams);
        $this->assertArrayHasKey('replica', $parsedParams);
        $this->assertArrayHasKey('replica1', $parsedParams['replica']);

        $this->assertSame('primary_test', $parsedParams['primary']['dbname']);
        $this->assertSame('replica_test', $parsedParams['replica']['replica1']['dbname']);
    }
}

/**
 * FakeDriver class to simulate a problem discussed in DoctrineBundle issue #673
 * In order to not use a real database driver we have to create our own fake/mock implementation.
 *
 * @link https://github.com/doctrine/DoctrineBundle/issues/673
 */
class FakeDriver implements Driver
{
    /**
     * Exception Mock
     *
     * @var DriverException
     */
    public static $exception;

    /** @var AbstractPlatform|null */
    public static $platform;

    /**
     * This method gets called to determine the database version which in our case leeds to the problem.
     * So we have to fake the exception a driver would normally throw.
     *
     * @link https://github.com/doctrine/DoctrineBundle/issues/673
     *
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress UndefinedClass
     * @psalm-suppress InvalidClass
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        if (self::$exception !== null) {
            throw self::$exception;
        }

        return static::$platform ?? new MySQLPlatform();
    }

    // ----- below this line follow only dummy methods to satisfy the interface requirements ----

    /**
     * {@inheritdoc}
     *
     * @param mixed[]     $params
     * @param string|null $username
     * @param string|null $password
     * @param mixed[]     $driverOptions
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []): Connection
    {
        throw new Exception('not implemented');
    }

    public function getSchemaManager(Connection $conn, ?AbstractPlatform $platform = null): AbstractSchemaManager
    {
        throw new Exception('not implemented');
    }

    /**
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress MissingDependency
     * @psalm-suppress UndefinedClass
     */
    public function getExceptionConverter(): ExceptionConverter
    {
        return new class implements ExceptionConverter {
            public function convert(InternalDriverException $exception, ?Query $query): DriverException
            {
                return new DriverException($exception, $query);
            }
        };
    }

    public function getName(): string
    {
        return 'FakeDriver';
    }

    public function getDatabase(Connection $conn): string
    {
        return 'fake_db';
    }
}

class FakeConnection extends Connection
{
    /** @var int */
    public static $creationCount = 0;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $params, FakeDriver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        ++self::$creationCount;

        parent::__construct($params, $driver, $config, $eventManager);
    }
}
