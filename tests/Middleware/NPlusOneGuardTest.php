<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Exception\NPlusOneQueriesDetected;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

use function fopen;

class NPlusOneGuardTest extends TestCase
{
    public function testStaysQuietBelowTheThreshold(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 3], [], $logger);

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [1]);
        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [2]);

        $this->assertSame([], $logger->warnings);
    }

    public function testReportsOnceWhenTheThresholdIsReached(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 3], [], $logger);

        foreach ([1, 2, 3, 4, 5] as $authorId) {
            $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [$authorId]);
        }

        $this->assertCount(1, $logger->warnings);
        $this->assertStringContainsString('replayed with 3 different parameter sets', $logger->warnings[0]);
        $this->assertStringContainsString('SELECT * FROM post WHERE author_id = ?', $logger->warnings[0]);
    }

    public function testIgnoresAStatementReplayedWithTheSameParameters(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 2], [], $logger);

        foreach ([1, 1, 1, 1] as $authorId) {
            $guard->track('default', 'SELECT * FROM author WHERE id = ?', [$authorId]);
        }

        $this->assertSame([], $logger->warnings);
    }

    public function testIsDisabledWithoutAThreshold(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard([], [], $logger);

        foreach ([1, 2, 3] as $authorId) {
            $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [$authorId]);
        }

        $this->assertSame([], $logger->warnings);
    }

    public function testCountsEachConnectionOnItsOwn(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 2], [], $logger);

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [1]);
        $guard->track('other', 'SELECT * FROM post WHERE author_id = ?', [2]);

        $this->assertSame([], $logger->warnings);
    }

    public function testThrowsInStrictMode(): void
    {
        $guard = new NPlusOneGuard(['default' => 2], ['default' => true]);

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [1]);

        $this->expectException(NPlusOneQueriesDetected::class);
        $this->expectExceptionMessage('replayed with 2 different parameter sets on connection "default"');

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [2]);
    }

    public function testResetForgetsWhatItSaw(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 2], [], $logger);

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [1]);
        $guard->reset();
        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [2]);

        $this->assertSame([], $logger->warnings);
    }

    public function testTellsDistinctResourcesApart(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 2], [], $logger);

        $first  = fopen('php://memory', 'r+');
        $second = fopen('php://memory', 'r+');

        $guard->track('default', 'SELECT * FROM post WHERE data = ?', [$first]);
        $guard->track('default', 'SELECT * FROM post WHERE data = ?', [$second]);

        $this->assertCount(1, $logger->warnings);
    }

    public function testHandlesUnserializableParameters(): void
    {
        $logger = $this->createLogger();
        $guard  = new NPlusOneGuard(['default' => 2], [], $logger);

        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [1, static fn () => null]);
        $guard->track('default', 'SELECT * FROM post WHERE author_id = ?', [2, static fn () => null]);

        $this->assertCount(1, $logger->warnings);
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
