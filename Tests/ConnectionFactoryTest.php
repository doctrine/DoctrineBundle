<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;

use function array_intersect_key;

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
        $this->assertSame('utf8', $connection->getParams()['charset']);
        $this->assertSame(1 + $creationCount, FakeConnection::$creationCount);
    }

    public function testDefaultCharsetMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $params  = ['driver' => 'pdo_mysql'];

        $connection = $factory->createConnection($params, $this->configuration);

        $this->assertSame('utf8mb4', $connection->getParams()['charset']);
    }

    public function testDefaultCollationMySql(): void
    {
        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection(['driver' => 'pdo_mysql'], $this->configuration);

        $this->assertSame(
            'utf8mb4_unicode_ci',
            $connection->getParams()['defaultTableOptions']['collation'],
        );
    }

    /** @group legacy */
    public function testCollateMapsToCollationForMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $this->expectDeprecationWithIdentifier(
            'https://github.com/doctrine/dbal/issues/5214',
        );
        $connection = $factory->createConnection(
            [
                'driver' => 'pdo_mysql',
                'defaultTableOptions' => ['collate' => 'my_collation'],
            ],
            $this->configuration,
        );

        $tableOptions = $connection->getParams()['defaultTableOptions'];
        $this->assertArrayNotHasKey('collate', $tableOptions);
        $this->assertSame(
            'my_collation',
            $tableOptions['collation'],
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

        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
                'url' => 'mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8',
                'connection_override_options' => $params,
            ],
            $this->configuration,
        );

        $this->assertEquals($params, array_intersect_key($connection->getParams(), $params));
    }

    public function testConnectionCharsetFromUrl()
    {
        /** @psalm-suppress InvalidArgument Need to be compatible with DBAL < 4, which still has `$params['url']` */
        $connection = (new ConnectionFactory([]))->createConnection(
            ['url' => 'mysql://root:password@database:3306/main?charset=utf8mb4_unicode_ci'],
            $this->configuration,
        );

        $this->assertEquals('utf8mb4_unicode_ci', $connection->getParams()['charset']);
    }

    public function testDbnameSuffix(): void
    {
        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
                'url' => 'mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8',
                'dbname_suffix' => '_test',
            ],
            $this->configuration,
        );

        $this->assertSame('main_test', $connection->getParams()['dbname']);
    }

    public function testDbnameSuffixForReplicas(): void
    {
        /** @psalm-suppress InvalidArgument We should adjust when https://github.com/vimeo/psalm/issues/8984 is fixed */
        $connection = (new ConnectionFactory([]))->createConnection(
            [
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
            ],
            $this->configuration,
        );

        $parsedParams = $connection->getParams();
        $this->assertArrayHasKey('primary', $parsedParams);
        $this->assertArrayHasKey('replica', $parsedParams);
        $this->assertArrayHasKey('replica1', $parsedParams['replica']);

        $this->assertSame('primary_test', $parsedParams['primary']['dbname']);
        $this->assertSame('replica_test', $parsedParams['replica']['replica1']['dbname']);
    }
}

class FakeConnection extends Connection
{
    public static int $creationCount = 0;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $params, Driver $driver, ?Configuration $config = null)
    {
        ++self::$creationCount;

        parent::__construct($params, $driver, $config);
    }
}
