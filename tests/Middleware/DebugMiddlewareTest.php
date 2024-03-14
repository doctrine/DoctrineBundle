<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

use function class_exists;
use function sprintf;
use function strpos;

/** @psalm-suppress MissingDependency */
class DebugMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(DebugDataHolder::class)) {
            return;
        }

        $this->markTestSkipped(sprintf('This test needs %s to exist', DebugDataHolder::class));
    }

    public function testData(): void
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $debugDataHolder = new BacktraceDebugDataHolder(['default']);
        $configuration->setMiddlewares([new DebugMiddleware($debugDataHolder, null)]);

        $conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration);

        $conn->executeQuery(<<<'EOT'
CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    price REAL NOT NULL,
    stock INTEGER NOT NULL
);
EOT);

        $data = $debugDataHolder->getData();
        $this->assertCount(1, $data['default'] ?? []);

        $current = $data['default'][0];

        $this->assertSame(0, strpos($current['sql'] ?? '', 'CREATE TABLE products'));
        $this->assertSame([], $current['params'] ?? null);
        $this->assertSame([], $current['types'] ?? null);
        $this->assertGreaterThan(0, $current['executionMS'] ?? 0);
        $this->assertSame(Connection::class, $current['backtrace'][0]['class'] ?? '');
        $this->assertSame('executeQuery', $current['backtrace'][0]['function'] ?? '');

        $debugDataHolder->reset();
        $data = $debugDataHolder->getData();
        $this->assertCount(0, $data['default'] ?? []);
    }
}
