<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use ArrayObject;
use Doctrine\Bundle\DoctrineBundle\Middleware\IdleConnectionMiddleware;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver as IdleConnectionDriver;

use function time;

class IdleConnectionMiddlewareTest extends TestCase
{
    #[RequiresMethod(IdleConnectionDriver::class, '__construct')]
    public function testWrap(): void
    {
        /** @var ArrayObject<string, int> $connectionExpiries */
        $connectionExpiries = new ArrayObject(['connectionone' => time() - 30, 'connectiontwo' => time() + 40]);
        $ttlByConnection    = ['connectionone' => 25, 'connectiontwo' => 60];

        $middleware = new IdleConnectionMiddleware($connectionExpiries, $ttlByConnection);
        $middleware->setConnectionName('connectionone');

        $driverMock    = $this->createStub(Driver::class);
        $wrappedDriver = $middleware->wrap($driverMock);

        $this->assertInstanceOf(IdleConnectionDriver::class, $wrappedDriver);
    }
}
