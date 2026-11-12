<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

use function strpos;

class DebugMiddlewareTest extends TestCase
{
    public function testQueryTimelineEvent(): void
    {
        $stopwatch     = new Stopwatch();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new DebugMiddleware(new BacktraceDebugDataHolder([]), $stopwatch)]);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration);

        $connection->executeQuery('SELECT 1');
        $connection->executeQuery('SELECT ?', [2]);

        $event = $stopwatch->getEvent('doctrine.query');
        self::assertSame('doctrine', $event->getCategory());
        self::assertCount(2, $event->getPeriods());
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
