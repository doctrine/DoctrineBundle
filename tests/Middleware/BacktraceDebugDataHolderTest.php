<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Exception\NPlusOneQueriesDetected;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\DBAL\ParameterType;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;

use function count;
use function strpos;

class BacktraceDebugDataHolderTest extends TestCase
{
    public function testAddAndRetrieveData(): void
    {
        $sut = new BacktraceDebugDataHolder([]);
        $sut->addQuery('myconn', new Query('SELECT * FROM product'));

        $data = $sut->getData();
        $this->assertCount(1, $data['myconn'] ?? []);
        $current = $data['myconn'][0];

        $this->assertSame(0, strpos($current['sql'] ?? '', 'SELECT * FROM product'));
        $this->assertSame([], $current['params'] ?? null);
        $this->assertSame([], $current['types'] ?? null);
    }

    public function testReset(): void
    {
        $sut = new BacktraceDebugDataHolder([]);
        $sut->addQuery('myconn', new Query('SELECT * FROM product'));

        $this->assertCount(1, $sut->getData()['myconn'] ?? []);
        $sut->reset();
        $this->assertCount(0, $sut->getData()['myconn'] ?? []);
    }

    public function testQueriesAreHandedToTheNPlusOneGuard(): void
    {
        $sut = new BacktraceDebugDataHolder([], new NPlusOneGuard(['myconn' => 2], ['myconn' => true]));

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 1));

        $this->expectException(NPlusOneQueriesDetected::class);

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 2));
    }

    public function testResettingTheHolderAlsoResetsTheGuard(): void
    {
        $guard = new NPlusOneGuard(['myconn' => 2], ['myconn' => true]);
        $sut   = new BacktraceDebugDataHolder([], $guard);

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 1));
        $sut->reset();

        // Without the reset reaching the guard, the second parameter set would
        // reach the threshold and throw.
        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 2));

        $this->assertCount(1, $sut->getData()['myconn'] ?? []);
    }

    public function testAStrictGuardDoesNotShiftTheBacktraces(): void
    {
        $guard = new NPlusOneGuard(['myconn' => 2], ['myconn' => true]);
        $sut   = new BacktraceDebugDataHolder(['myconn'], $guard);

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 1));

        try {
            $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 2));
            $this->fail('The guard was expected to throw.');
        } catch (NPlusOneQueriesDetected) {
        }

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM category WHERE id = ?', 3));

        // The throw must not leave a record without the backtrace it is read by.
        $data = $sut->getData();
        $this->assertCount(3, $data['myconn'] ?? []);

        foreach ($data['myconn'] as $record) {
            $this->assertGreaterThan(0, count($record['backtrace'] ?? []));
        }
    }

    public function testWithoutAGuardQueriesAreOnlyRecorded(): void
    {
        $sut = new BacktraceDebugDataHolder([]);

        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 1));
        $sut->addQuery('myconn', $this->createQuery('SELECT * FROM product WHERE category_id = ?', 2));

        $this->assertCount(2, $sut->getData()['myconn'] ?? []);
    }

    private function createQuery(string $sql, int $param): Query
    {
        $query = new Query($sql);
        $query->setValue(1, $param, ParameterType::INTEGER);

        return $query;
    }

    public function testBacktracesEnabled(): void
    {
        $sut = new BacktraceDebugDataHolder(['myconn2']);
        $this->funcForBacktraceGeneration($sut);

        $data = $sut->getData();
        $this->assertCount(1, $data['myconn1'] ?? []);
        $this->assertCount(1, $data['myconn2'] ?? []);
        $currentConn1 = $data['myconn1'][0];
        $currentConn2 = $data['myconn2'][0];

        $this->assertCount(0, $currentConn1['backtrace'] ?? []);
        $this->assertGreaterThan(0, count($currentConn2['backtrace'] ?? []));

        $lastCall = $currentConn2['backtrace'][0];
        $this->assertSame(self::class, $lastCall['class']);
        $this->assertSame(__FUNCTION__, $lastCall['function']);
    }

    private function funcForBacktraceGeneration(BacktraceDebugDataHolder $sut): void
    {
        $sut->addQuery('myconn1', new Query('SELECT * FROM product'));
        $sut->addQuery('myconn2', new Query('SELECT * FROM car'));
    }
}
