<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use ArrayObject;
use Doctrine\Bundle\DoctrineBundle\Middleware\IdleConnectionMiddleware;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver as IdleConnectionDriver;

use function time;

class IdleConnectionMiddlewareTest extends TestCase
{
    /** @requires function Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver::__construct */
    public function testWrap()
    {
        $connectionExpiries = new ArrayObject(['connectionone' => time() - 30, 'connectiontwo' => time() + 40]);
        $ttlByConnection    = ['connectionone' => 25, 'connectiontwo' => 60];

        $middleware = new IdleConnectionMiddleware($connectionExpiries, $ttlByConnection);
        $middleware->setConnectionName('connectionone');

        $driverMock    = $this->createMock(Driver::class);
        $wrappedDriver = $middleware->wrap($driverMock);

        $this->assertInstanceOf(IdleConnectionDriver::class, $wrappedDriver);
    }
}
