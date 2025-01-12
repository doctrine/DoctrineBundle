<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
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
