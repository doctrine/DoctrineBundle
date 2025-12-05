<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;

class ConnectionFactoryTest extends TestCase
{
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
        $params = $connection->getParams();
        /** @var array<string, mixed> $params */
        $this->assertSame('utf8', $params['charset']);
        $this->assertSame(1 + $creationCount, FakeConnection::$creationCount);
    }

    public function testDefaultCharsetMySql(): void
    {
        $factory = new ConnectionFactory([]);
        $params  = ['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'];

        $connection = $factory->createConnection($params, $this->configuration);

        $params = $connection->getParams();
        /** @var array<string, mixed> $params */
        $this->assertSame('utf8mb4', $params['charset']);
    }

    public function testDefaultCollationMySql(): void
    {
        $factory    = new ConnectionFactory([]);
        $connection = $factory->createConnection(['driver' => 'pdo_mysql', 'serverVersion' => '9.4.0'], $this->configuration);

        $params = $connection->getParams();
        /** @var array<string, mixed> $params */
        $defaultTableOptions = $params['defaultTableOptions'];
        $this->assertIsArray($defaultTableOptions);
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
        /** @var array<string, mixed> $params */
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
        /** @var array<string, mixed> $params */
        $this->assertSame('main_test', $params['dbname']);
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

        $parsedParams = $connection->getParams();
        /** @var array<string, mixed> $parsedParams */
        $this->assertArrayHasKey('primary', $parsedParams);
        $this->assertArrayHasKey('replica', $parsedParams);
        $this->assertArrayHasKey('replica1', $parsedParams['replica']);

        $this->assertArrayHasKey('dbname', $parsedParams['primary']);
        $this->assertArrayHasKey('dbname', $parsedParams['replica']['replica1']);

        $primary = $parsedParams['primary'];
        /** @var array<string, mixed> $primary */
        $replica = $parsedParams['replica'];
        /** @var array<string, mixed> $replica */
        $replica1 = $replica['replica1'];
        /** @var array<string, mixed> $replica1 */

        $this->assertSame('primary_test', $primary['dbname']);
        $this->assertSame('replica_test', $replica1['dbname']);
    }
}

class FakeConnection extends Connection
{
    public static int $creationCount = 0;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $params, Driver $driver, Configuration|null $config = null)
    {
        ++self::$creationCount;

        parent::__construct($params, $driver, $config);
    }
}
