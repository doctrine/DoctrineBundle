<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bridge\Doctrine\Messenger\DoctrineClearEntityManagerWorkerSubscriber;
use Symfony\Bridge\Doctrine\Messenger\DoctrineCloseConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalCloseConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalOpenTransactionLoggerMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalPingConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineDbalTransactionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineOpenTransactionLoggerMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\EventListener\PostgreSqlNotifyOnIdleListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;

use function class_exists;

return static function (ContainerConfigurator $container): void {
    // symfony/doctrine-bridge 8.2 deprecates the non-DBAL classes, so the legacy service ids use the DBAL ones when they exist
    $pingMiddleware                  = class_exists(DoctrineDbalPingConnectionMiddleware::class) ? DoctrineDbalPingConnectionMiddleware::class : DoctrinePingConnectionMiddleware::class;
    $closeMiddleware                 = class_exists(DoctrineDbalCloseConnectionMiddleware::class) ? DoctrineDbalCloseConnectionMiddleware::class : DoctrineCloseConnectionMiddleware::class;
    $openTransactionLoggerMiddleware = class_exists(DoctrineDbalOpenTransactionLoggerMiddleware::class) ? DoctrineDbalOpenTransactionLoggerMiddleware::class : DoctrineOpenTransactionLoggerMiddleware::class;
    // The deprecated class takes the entity manager name as second argument, the DBAL one does not
    $openTransactionLoggerArgs = $openTransactionLoggerMiddleware === DoctrineDbalOpenTransactionLoggerMiddleware::class
        ? [service('doctrine'), service('logger')]
        : [service('doctrine'), null, service('logger')];

    $container->services()

        ->set('messenger.middleware.doctrine_transaction', DoctrineTransactionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_dbal_transaction', DoctrineDbalTransactionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_ping_connection', $pingMiddleware)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_dbal_ping_connection', DoctrineDbalPingConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_close_connection', $closeMiddleware)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_dbal_close_connection', DoctrineDbalCloseConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_open_transaction_logger', $openTransactionLoggerMiddleware)
            ->abstract()
            ->args($openTransactionLoggerArgs)

        ->set('messenger.middleware.doctrine_dbal_open_transaction_logger', DoctrineDbalOpenTransactionLoggerMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
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
