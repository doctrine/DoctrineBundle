<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bridge\Doctrine\Messenger\DoctrineClearEntityManagerWorkerSubscriber;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalCloseConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalOpenTransactionLoggerMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalPingConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineCloseConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineOpenTransactionLoggerMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\EventListener\PostgreSqlNotifyOnIdleListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;

return static function (ContainerConfigurator $container): void {
    // symfony/doctrine-bridge 8.2 deprecates these classes in favor of the DoctrineDbal* ones
    $pingMiddleware = class_exists(DoctrineDbalPingConnectionMiddleware::class) ? DoctrineDbalPingConnectionMiddleware::class : DoctrinePingConnectionMiddleware::class;
    $closeMiddleware = class_exists(DoctrineDbalCloseConnectionMiddleware::class) ? DoctrineDbalCloseConnectionMiddleware::class : DoctrineCloseConnectionMiddleware::class;
    $openTransactionLoggerMiddleware = class_exists(DoctrineDbalOpenTransactionLoggerMiddleware::class) ? DoctrineDbalOpenTransactionLoggerMiddleware::class : DoctrineOpenTransactionLoggerMiddleware::class;

    $container->services()

        ->set('messenger.middleware.doctrine_transaction', DoctrineTransactionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_ping_connection', $pingMiddleware)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_close_connection', $closeMiddleware)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_open_transaction_logger', $openTransactionLoggerMiddleware)
            ->abstract()
            ->args([
                service('doctrine'),
                null,
                service('logger'),
            ])

        ->set('doctrine.orm.messenger.event_subscriber.doctrine_clear_entity_manager', DoctrineClearEntityManagerWorkerSubscriber::class)
            ->tag('kernel.event_subscriber')
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.transport.doctrine.factory', DoctrineTransportFactory::class)
            ->tag('messenger.transport_factory')
            ->args([
                service('doctrine'),
                service('messenger.transport.doctrine.pg_notify_on_idle_listener')->ignoreOnInvalid(),
            ])

        ->set('doctrine.orm.messenger.doctrine_schema_listener', MessengerTransportDoctrineSchemaListener::class)
            ->args([
                tagged_iterator('messenger.receiver'),
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])
            ->tag('doctrine.event_listener', ['event' => 'onSchemaCreateTable'])

        ->set('messenger.transport.doctrine.pg_notify_on_idle_listener', PostgreSqlNotifyOnIdleListener::class)
            ->args([
                service('logger'),
                service('clock')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber');
};
