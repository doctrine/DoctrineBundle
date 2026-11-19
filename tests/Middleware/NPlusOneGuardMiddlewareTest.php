<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Exception\NPlusOneQueriesDetected;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuardMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class NPlusOneGuardMiddlewareTest extends TestCase
{
    public function testReportsAStatementReplayedWithDifferentParameters(): void
    {
        $logger = $this->createLogger();
        $conn   = $this->createConnection(new NPlusOneGuard(['default' => 3], [], $logger));

        foreach ([1, 2, 3, 4] as $categoryId) {
            $conn->executeQuery('SELECT id FROM product WHERE category_id = ?', [$categoryId]);
        }

        $this->assertCount(1, $logger->warnings);
        $this->assertStringContainsString('SELECT id FROM product WHERE category_id = ?', $logger->warnings[0]);
    }

    public function testThrowsInStrictModeBeforeTheStatementRuns(): void
    {
        $conn = $this->createConnection(new NPlusOneGuard(['default' => 2], ['default' => true]));

        $conn->executeQuery('SELECT id FROM product WHERE category_id = ?', [1]);

        $this->expectException(NPlusOneQueriesDetected::class);

        $conn->executeQuery('SELECT id FROM product WHERE category_id = ?', [2]);
    }

    public function testLeavesTheSameStatementWithTheSameParametersAlone(): void
    {
        $logger = $this->createLogger();
        $conn   = $this->createConnection(new NPlusOneGuard(['default' => 2], [], $logger));

        foreach ([1, 1, 1] as $categoryId) {
            $conn->executeQuery('SELECT id FROM product WHERE category_id = ?', [$categoryId]);
        }

        $conn->executeStatement('INSERT INTO product (id, category_id) VALUES (?, ?)', [10, 1]);
        $conn->executeStatement('INSERT INTO product (id, category_id) VALUES (?, ?)', [11, 2]);

        $this->assertSame([], $logger->warnings);
    }

    private function createConnection(NPlusOneGuard $guard): Connection
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $middleware = new NPlusOneGuardMiddleware($guard);
        $middleware->setConnectionName('default');
        $configuration->setMiddlewares([$middleware]);

        $conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration);

        $conn->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL)');

        return $conn;
    }

    /** @return AbstractLogger&object{warnings: list<string>} */
    private function createLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            /** @param mixed[] $context */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                if ($level !== LogLevel::WARNING) {
                    return;
                }

                $this->warnings[] = (string) $message;
            }
        };
    }
}
