<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DbalSchemaFilterPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterDbalTypePass;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\DbalTestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\WorkerWarmer\ConnectionWarmer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;

use function assert;
use function interface_exists;
use function is_a;
use function method_exists;
use function restore_exception_handler;

class BundleTest extends TestCase
{
    public function testConnectionsAreClosedAfterEntityManagersAreCleared(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new TestKernel();
        $kernel->boot();
        restore_exception_handler();

        $manager = $kernel->getContainer()->get('doctrine')->getManager();
        assert($manager instanceof EntityManagerInterface);
        $connection = $manager->getConnection();
        $manager->getEventManager()->addEventListener([Events::onClear], new class ($connection) {
            public function __construct(private readonly Connection $connection)
            {
            }

            public function onClear(): void
            {
                $this->connection->executeQuery('SELECT 1');
            }
        });

        $kernel->shutdown();

        $this->assertFalse($connection->isConnected());
    }

    public function testShutdownClosesConnectionsWithoutInitializingUnusedConnections(): void
    {
        $kernel = new DbalTestKernel([
            'connections' => [
                'default' => ['driver' => 'pdo_sqlite'],
                'unused' => ['driver' => 'pdo_sqlite'],
            ],
        ]);
        $kernel->boot();
        restore_exception_handler();

        $container  = $kernel->getContainer();
        $connection = $container->get('doctrine.dbal.default_connection');
        $connection->executeQuery('SELECT 1');

        $kernel->shutdown();

        $this->assertFalse($connection->isConnected());
        $this->assertFalse($container->initialized('doctrine.dbal.unused_connection'));
    }

    #[RequiresMethod(ConnectionWarmer::class, '__invoke')]
    public function testWorkerWarmerOpensPreconnectedConnections(): void
    {
        $kernel = new DbalTestKernel([
            'connections' => [
                'default' => ['driver' => 'pdo_sqlite', 'memory' => true, 'preconnect' => true],
                'lazy' => ['driver' => 'pdo_sqlite', 'memory' => true],
            ],
        ]);
        $kernel->boot();
        restore_exception_handler();

        $container = $kernel->getContainer();
        $this->assertFalse($container->initialized('doctrine.dbal.default_connection'));

        $warmer = $container->get('worker_warmer');
        $this->assertTrue(method_exists($warmer, 'warmUp'));
        $warmer->warmUp();

        $this->assertTrue($container->get('doctrine.dbal.default_connection')->isConnected());
        $this->assertFalse($container->initialized('doctrine.dbal.lazy_connection'));

        $kernel->shutdown();
    }

    public function testResetKeepsConnectionsUsable(): void
    {
        $kernel = new DbalTestKernel();
        $kernel->boot();
        restore_exception_handler();

        $container  = $kernel->getContainer();
        $connection = $container->get('doctrine.dbal.default_connection');
        $connection->executeQuery('SELECT 1');

        $container->get('services_resetter')->reset();

        $this->assertTrue($connection->isConnected());
        $this->assertSame(1, $connection->fetchOne('SELECT 1'));

        $kernel->shutdown();
    }

    public function testBundleShutdownDefersClosingConnectionsToTheContainer(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (! is_a(ServicesResetterInterface::class, 'Symfony\Contracts\Service\ShutdownInterface', true)) {
            self::markTestSkipped('This test requires Symfony 8.2.');
        }

        $kernel = new DbalTestKernel();
        $kernel->boot();
        restore_exception_handler();

        $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeQuery('SELECT 1');

        $kernel->getBundle('DoctrineBundle')->shutdown();

        $this->assertTrue($connection->isConnected());

        $kernel->shutdown();

        $this->assertFalse($connection->isConnected());
    }

    public function testBuildCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        $bundle    = new DoctrineBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundEventListener    = false;
        $foundValidation       = false;
        $foundSchemaFilter     = false;
        $foundRegisterDbalType = false;

        foreach ($passes as $pass) {
            if ($pass instanceof RegisterEventListenersAndSubscribersPass) {
                $foundEventListener = true;
            } elseif ($pass instanceof DoctrineValidationPass) {
                $foundValidation = true;
            } elseif ($pass instanceof DbalSchemaFilterPass) {
                $foundSchemaFilter = true;
            } elseif ($pass instanceof RegisterDbalTypePass) {
                $foundRegisterDbalType = true;
            }
        }

        $this->assertTrue($foundEventListener, 'RegisterEventListenersAndSubscribersPass was not found');
        $this->assertTrue($foundValidation, 'DoctrineValidationPass was not found');
        $this->assertTrue($foundSchemaFilter, 'DbalSchemaFilterPass was not found');
        $this->assertTrue($foundRegisterDbalType, 'RegisterDbalTypePass was not found');
    }
}
