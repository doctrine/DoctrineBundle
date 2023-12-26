<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\CheckConnection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Based on https://github.com/Baldinof/roadrunner-bundle/blob/3.x/src/Integration/Doctrine/DoctrineORMMiddleware.php
 */
class ConnectionKeepAliveMiddlewareTest extends TestCase
{
    public const CONNECTION_NAME = 'doctrine.connection';
    public const MANAGER_NAME    = 'doctrine.manager';

    private MockObject&ManagerRegistry $managerRegistryMock;
    private MockObject&Connection $connectionMock;
    private ContainerInterface $container;
    private MockObject&Driver $driver;
    private DoctrineConnectionDriverTest $doctrineConnectionDriver;

    public function setUp(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');

        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('getDatabasePlatform')->willReturn($platform);
        $connectionDriverMock = $this->createMock(Driver\Connection::class);

        $this->container = new Container();
        $this->container->set(self::CONNECTION_NAME, $this->connectionMock);

        $this->managerRegistryMock->method('getConnectionNames')->willReturn([self::CONNECTION_NAME]);
        $this->managerRegistryMock->method('getManagerNames')->willReturn([self::MANAGER_NAME]);

        $this->driver = $this->createMock(Driver::class);
        $this->driver->method('connect')->willReturn($connectionDriverMock);

        $this->doctrineConnectionDriver = new DoctrineConnectionDriverTest($this->driver, $this->managerRegistryMock, $this->container);
    }

    public function testSkipNotInitializedConnections()
    {
        $this->container->set(self::CONNECTION_NAME, null);

        $this->connectionMock->expects($this->never())->method('isConnected');
        $this->connectionMock->expects($this->never())->method('executeQuery');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->doctrineConnectionDriver->connect([]);
    }

    public function testSkipWhenNotConnected(): void
    {
        $this->connectionMock->method('isConnected')->willReturn(false);
        $this->connectionMock->expects($this->never())->method('executeQuery');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->doctrineConnectionDriver->connect([]);
    }

    public function testItClosesNotPingableConnection(): void
    {
        $this->connectionMock->expects($this->exactly(2))->method('executeQuery')
            ->willReturnCallback(function () {
                static $counter = 0;

                if (1 === ++$counter) {
                    throw $this->createMock(DBALException::class);
                }

                return $this->createMock(Result::class);
            });

        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->once())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->doctrineConnectionDriver->connect([]);
    }

    public function testItDoesNotClosePingableConnection(): void
    {
        $this->connectionMock->expects($this->once())->method('executeQuery');
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->doctrineConnectionDriver->connect([]);
    }

    public function testItForcesRebootOnClosedManagerWhenMissingProxySupport()
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $this->container->set(self::MANAGER_NAME, $manager);

        $manager->expects($this->once())->method('isOpen')->willReturn(false);
        $this->managerRegistryMock->expects($this->once())
            ->method('resetManager')
            ->with(self::MANAGER_NAME);

        $this->doctrineConnectionDriver->connect([]);
    }
}

class DoctrineConnectionDriverTest extends AbstractDriverMiddleware
{
    private Driver $driver;
    private ManagerRegistry $managerRegistry;
    private ContainerInterface $container;

    public function __construct(Driver $driver, ManagerRegistry $managerRegistry, ContainerInterface $container)
    {
        $this->driver          = $driver;
        $this->managerRegistry = $managerRegistry;
        $this->container       = $container;

        parent::__construct($driver);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(array $params): DriverConnection
    {
        $connectionServices = $this->managerRegistry->getConnectionNames();

        foreach ($connectionServices as $connectionServiceName) {
            if (! $this->container->initialized($connectionServiceName)) {
                continue;
            }

            $connection = $this->container->get($connectionServiceName);

            if (! $connection instanceof Connection) {
                continue;
            }

            if ($connection->isConnected()) {
                CheckConnection::reconnectOnFailure($connection);
            }

            $managerNames = $this->managerRegistry->getManagerNames();

            foreach ($managerNames as $managerName) {
                if (! $this->container->initialized($managerName)) {
                    continue;
                }

                $manager = $this->container->get($managerName);

                if (! $manager instanceof EntityManagerInterface) {
                    continue;
                }

                if (! $manager->isOpen()) {
                    $this->managerRegistry->resetManager($managerName);
                }
            }
        }

        return parent::connect($params);
    }
}
