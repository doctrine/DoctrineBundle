<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bridge\Doctrine\Messenger\DoctrineCloseConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineOpenTransactionLoggerMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\Messenger\DoctrineClearEntityManagerWorkerSubscriber;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set('messenger.middleware.doctrine_transaction', DoctrineTransactionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_ping_connection', DoctrinePingConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_close_connection', DoctrineCloseConnectionMiddleware::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('messenger.middleware.doctrine_open_transaction_logger', DoctrineOpenTransactionLoggerMiddleware::class)
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
            ])

        ->set('doctrine.orm.messenger.doctrine_schema_listener', MessengerTransportDoctrineSchemaListener::class)
            ->args([
                tagged_iterator('messenger.receiver'),
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])
            ->tag('doctrine.event_listener', ['event' => 'onSchemaCreateTable'])

        ;
};
