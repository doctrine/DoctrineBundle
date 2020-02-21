<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Exception;

class ConnectionFactoryTest extends TestCase
{
    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testContainer() : void
    {
        $typesConfig  = [];
        $factory      = new ConnectionFactory($typesConfig);
        $params       = ['driverClass' => FakeDriver::class];
        $config       = null;
        $eventManager = null;
        $mappingTypes = [0];
        $exception    = new DriverException('', $this->createMock(Driver\AbstractDriverException::class));

        // put the mock into the fake driver
        FakeDriver::$exception = $exception;

        try {
            $factory->createConnection($params, $config, $eventManager, $mappingTypes);
        } catch (Exception $e) {
            $this->assertTrue(strpos($e->getMessage(), 'can circumvent this by setting') > 0);
            throw $e;
        } finally {
            FakeDriver::$exception = null;
        }
    }

    public function testDefaultCharset() : void
    {
        $factory = new ConnectionFactory([]);
        $params  = [
            'driverClass' => FakeDriver::class,
            'wrapperClass' => FakeConnection::class,
        ];

        $creationCount = FakeConnection::$creationCount;
        $connection    = $factory->createConnection($params);

        $this->assertInstanceof(FakeConnection::class, $connection);
        $this->assertSame('utf8', $connection->getParams()['charset']);
        $this->assertSame(1 + $creationCount, FakeConnection::$creationCount);
    }

    public function testDefaultCharsetMySql() : void
    {
        $factory = new ConnectionFactory([]);
        $params  = ['driver' => 'pdo_mysql'];

        $connection = $factory->createConnection($params);

        $this->assertSame('utf8mb4', $connection->getParams()['charset']);
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
     */
    public function getDatabasePlatform() : AbstractPlatform
    {
        if (self::$exception !== null) {
            throw self::$exception;
        }

        return static::$platform ?? new MySqlPlatform();
    }

    // ----- below this line follow only dummy methods to satisfy the interface requirements ----

    /**
     * @param mixed[]     $params
     * @param string|null $username
     * @param string|null $password
     * @param mixed[]     $driverOptions
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []) : void
    {
        throw new Exception('not implemented');
    }

    public function getSchemaManager(Connection $conn) : void
    {
        throw new Exception('not implemented');
    }

    public function getName() : string
    {
        return 'FakeDriver';
    }

    public function getDatabase(Connection $conn) : string
    {
        return 'fake_db';
    }
}

class FakeConnection extends Connection
{
    /** @var int */
    public static $creationCount = 0;

    public function __construct(array $params, FakeDriver $driver, ?Configuration $config = null, ?EventManager $eventManager = null) : void
    {
        ++self::$creationCount;

        parent::__construct($params, $driver, $config, $eventManager);
    }
}
