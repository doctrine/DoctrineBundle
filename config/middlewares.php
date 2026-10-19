<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ArrayObject;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\Bundle\DoctrineBundle\Middleware\IdleConnectionMiddleware;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuardMiddleware;
use Doctrine\DBAL\Logging\Middleware;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set('doctrine.dbal.connection_expiries', ArrayObject::class)

        ->set('doctrine.dbal.logging_middleware', Middleware::class)
            ->abstract()
            ->args([
                service('logger'),
            ])
            ->tag('monolog.logger', ['channel' => 'doctrine'])

        ->set('doctrine.dbal.n_plus_one_guard', NPlusOneGuard::class)
            ->args([
                [],
                [],
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'doctrine'])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('doctrine.dbal.n_plus_one_guard_middleware', NPlusOneGuardMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine.dbal.n_plus_one_guard'),
            ])

        ->set('doctrine.debug_data_holder', BacktraceDebugDataHolder::class)
            ->args([
                [],
            ])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('doctrine.dbal.debug_middleware', DebugMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine.debug_data_holder'),
                service('debug.stopwatch')->nullOnInvalid(),
                service('doctrine'),
            ])

        ->set('doctrine.dbal.idle_connection_middleware', IdleConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine.dbal.connection_expiries'),
                null,
            ]);
};
