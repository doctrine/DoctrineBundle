<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ArrayObject;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\Bundle\DoctrineBundle\Middleware\IdleConnectionMiddleware;
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
            ])

        ->set('doctrine.dbal.idle_connection_middleware', IdleConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine.dbal.connection_expiries'),
                null,
            ])

        ;
};
