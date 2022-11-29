<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\MiddlewaresPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

use function sprintf;

use const PHP_VERSION_ID;

class MiddlewarePassTest extends TestCase
{
    /** @return array<string, array{0: class-string, 1: bool}> */
    public function provideAddMiddleware(): array
    {
        return [
            'not connection name aware' => [PHP7Middleware::class, false],
            'connection name aware' => [ConnectionAwarePHP7Middleware::class, true],
        ];
    }

    /** @dataProvider provideAddMiddleware */
    public function testAddMiddlewareWithExplicitTag(string $middlewareClass, bool $connectionNameAware): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container) use ($middlewareClass) {
            $container
                ->register('middleware', $middlewareClass)
                ->setAbstract(true)
                ->addTag('doctrine.middleware');

            $container
                ->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining

            $container
                ->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining
        });

        $this->assertMiddlewareInjected($container, 'conn1', $middlewareClass, $connectionNameAware);
        $this->assertMiddlewareInjected($container, 'conn2', $middlewareClass, $connectionNameAware);
    }

    public function testAddMiddlewareWithExplicitTagsOnSpecificConnections(): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container) {
            $container
                ->register('middleware', PHP7Middleware::class)
                ->setAbstract(true)
                ->addTag('doctrine.middleware', ['connection' => 'conn1']);

            $container
                ->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining

            $container
                ->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining
        });

        $this->assertMiddlewareInjected($container, 'conn1', PHP7Middleware::class);
        $this->assertMiddlewareNotInjected($container, 'conn2', PHP7Middleware::class);
    }

    public function testAddMiddlewareWithAutoconfigure(): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container) {
            $container
                ->register('middleware', AutoconfiguredPHP7Middleware::class)
                ->setAutoconfigured(true);

            $container
                ->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining

            $container
                ->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining
        });

        $this->assertMiddlewareInjected($container, 'conn1', AutoconfiguredPHP7Middleware::class);
        $this->assertMiddlewareInjected($container, 'conn2', AutoconfiguredPHP7Middleware::class);
    }

    /** @return array<string, array{0: class-string, 1: bool}> */
    public function provideAddMiddlewareWithAttributeForAutoconfiguration(): array
    {
        return [
            'without specifying connection' => [AutoconfiguredMiddleware::class, true],
            'specifying connection' => [AutoconfiguredMiddlewareWithConnection::class, false],
        ];
    }

    /**
     * @param class-string $className
     *
     * @dataProvider provideAddMiddlewareWithAttributeForAutoconfiguration
     * @requires PHP 8
     */
    public function testAddMiddlewareWithAttributeForAutoconfiguration(string $className, bool $registeredOnConn1): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container) use ($className) {
            $container
                ->register('middleware', $className)
                ->setAutoconfigured(true);

            $container
                ->setAlias('conf_conn1', 'doctrine.dbal.conn1_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining

            $container
                ->setAlias('conf_conn2', 'doctrine.dbal.conn2_connection.configuration')
                ->setPublic(true); // Avoid removal and inlining
        });

        if ($registeredOnConn1) {
            $this->assertMiddlewareInjected($container, 'conn1', $className);
        } else {
            $this->assertMiddlewareNotInjected($container, 'conn1', $className);
        }

        $this->assertMiddlewareInjected($container, 'conn2', $className);
    }

    /** @dataProvider provideAddMiddleware */
    public function testDontAddMiddlewareWhenDbalIsNotUsed(string $middlewareClass, bool $connectionNameAware): void
    {
        $container = $this->createContainer(static function (ContainerBuilder $container) use ($middlewareClass) {
            $container
                ->register('middleware', $middlewareClass)
                ->setAbstract(true)
                ->addTag('doctrine.middleware');
        }, false);

        $middlewareDefinitions = $container->findTaggedServiceIds('doctrine.middleware');

        // no middleware was created as child definition
        self::assertCount(0, $middlewareDefinitions);
    }

    private function createContainer(callable $func, bool $addConnections = true): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));

        $loggerDef = new Definition();
        $loggerDef->setClass(NullLogger::class);
        $container->setDefinition('logger', $loggerDef);

        $container->registerExtension(new DoctrineExtension());

        if ($addConnections) {
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'connections' => [
                        'conn1' => ['url' => 'mysql://user:pass@server1.tld:3306/db1'],
                        'conn2' => ['url' => 'mysql://user:pass@server2.tld:3306/db2'],
                    ],
                ],
            ]);
        }

        $container->addCompilerPass(new MiddlewaresPass());

        $func($container);

        $container->compile();

        return $container;
    }

    private function assertMiddlewareInjected(
        ContainerBuilder $container,
        string $connName,
        string $middlewareClass,
        bool $connectionNameAware = false
    ): void {
        $middlewareFound = $this->getMiddlewaresForConn($container, $connName, $middlewareClass);

        $this->assertCount(1, $middlewareFound, sprintf(
            'Middleware %s not injected in doctrine.dbal.%s_connection.configuration',
            $middlewareClass,
            $connName
        ));

        $callsFound = [];
        foreach ($middlewareFound[0]->getMethodCalls() as $call) {
            if ($call[0] !== 'setConnectionName') {
                continue;
            }

            $callsFound[] = $call;
        }

        if ($connectionNameAware) {
            $this->assertCount(1, $callsFound);
            $this->assertSame($callsFound[0][1][0] ?? null, $connName);
        } else {
            $this->assertCount(0, $callsFound);
        }
    }

    private function assertMiddlewareNotInjected(
        ContainerBuilder $container,
        string $connName,
        string $middlewareClass
    ): void {
        $middlewareFound = $this->getMiddlewaresForConn($container, $connName, $middlewareClass);

        $this->assertCount(0, $middlewareFound, sprintf(
            'Middleware %s injected in doctrine.dbal.%s_connection.configuration',
            $middlewareClass,
            $connName
        ));
    }

    /** @return Definition[] */
    private function getMiddlewaresForConn(ContainerBuilder $container, string $connName, string $middlewareClass): array
    {
        $calls            = $container->getDefinition('conf_' . $connName)->getMethodCalls();
        $middlewaresFound = [];
        foreach ($calls as $call) {
            if ($call[0] !== 'setMiddlewares' || ! isset($call[1][0])) {
                continue;
            }

            foreach ($call[1][0] as $middlewareDef) {
                if ($middlewareDef->getClass() !== $middlewareClass) {
                    continue;
                }

                $middlewaresFound[] = $middlewareDef;
            }
        }

        return $middlewaresFound;
    }
}

class PHP7Middleware
{
}

class ConnectionAwarePHP7Middleware implements ConnectionNameAwareInterface
{
    public function setConnectionName(string $name): void
    {
    }
}

class AutoconfiguredPHP7Middleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return $driver;
    }
}

if (PHP_VERSION_ID >= 80000) {
    #[AsMiddleware]
    class AutoconfiguredMiddleware
    {
    }

    #[AsMiddleware(connections: ['conn2'])]
    class AutoconfiguredMiddlewareWithConnection
    {
    }
}
