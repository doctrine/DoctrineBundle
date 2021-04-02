<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Dbal\Logging;

use Doctrine\Bundle\DoctrineBundle\Dbal\Logging\BacktraceLogger;
use PHPUnit\Framework\TestCase;

use function current;

class BacktraceLoggerTest extends TestCase
{
    public function testBacktraceLogged(): void
    {
        $logger = new BacktraceLogger();
        $logger->startQuery('SELECT column FROM table');
        $currentQuery = current($logger->queries);
        self::assertSame('SELECT column FROM table', $currentQuery['sql']);
        self::assertNull($currentQuery['params']);
        self::assertNull($currentQuery['types']);
        self::assertGreaterThan(0, $currentQuery['backtrace']);
    }
}
