<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\RunSqlDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Controller\ProfilerController;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\Bundle\DoctrineBundle\Dbal\SchemaAssetsFilterManager;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use Doctrine\Common\Persistence\ManagerRegistry as LegacyManagerRegistry;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\Listeners\MysqlSessionInit;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\LegacySchemaManagerFactory;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Listener;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('doctrine.dbal.configuration.class', Configuration::class)
        ->set('doctrine.data_collector.class', DoctrineDataCollector::class)
        ->set('doctrine.dbal.connection.event_manager.class', ContainerAwareEventManager::class)
        ->set('doctrine.dbal.connection_factory.class', ConnectionFactory::class)
        ->set('doctrine.dbal.events.mysql_session_init.class', MysqlSessionInit::class)
        ->set('doctrine.dbal.events.oracle_session_init.class', OracleSessionInit::class)
        ->set('doctrine.class', Registry::class)
        ->set('doctrine.entity_managers', [])
        ->set('doctrine.default_entity_manager', '');

    $container->services()

        ->alias(Connection::class, 'database_connection')
        ->alias(ManagerRegistry::class, 'doctrine')
        ->alias(LegacyManagerRegistry::class, 'doctrine')

        ->set('data_collector.doctrine', param('doctrine.data_collector.class'))
            ->args([
                service('doctrine'),
                true,
                service('doctrine.debug_data_holder')->nullOnInvalid(),
            ])
            ->tag('data_collector', ['template' => '@Doctrine/Collector/db.html.twig', 'id' => 'db', 'priority' => 250])

        ->set('doctrine.dbal.connection_factory', param('doctrine.dbal.connection_factory.class'))
            ->args([
                param('doctrine.dbal.connection_factory.types'),
                service('doctrine.dbal.connection_factory.dsn_parser'),
            ])

        ->set('doctrine.dbal.connection_factory.dsn_parser', DsnParser::class)
            ->args([
                [],
            ])

        ->set('doctrine.dbal.connection', Connection::class)
            ->abstract()
            ->factory([service('doctrine.dbal.connection_factory'), 'createConnection'])

        ->set('doctrine.dbal.connection.event_manager', param('doctrine.dbal.connection.event_manager.class'))
            ->abstract()
            ->args([
                service('service_container'),
            ])

        ->set('doctrine.dbal.connection.configuration', param('doctrine.dbal.configuration.class'))
            ->abstract()

        ->set('doctrine', param('doctrine.class'))
            ->public()
            ->args([
                service('service_container'),
                param('doctrine.connections'),
                param('doctrine.entity_managers'),
                param('doctrine.default_connection'),
                param('doctrine.default_entity_manager'),
            ])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('doctrine.twig.doctrine_extension', DoctrineExtension::class)
            ->tag('twig.extension')

        ->set('doctrine.dbal.schema_asset_filter_manager', SchemaAssetsFilterManager::class)
            ->abstract()

        ->set('doctrine.dbal.well_known_schema_asset_filter', BlacklistSchemaAssetFilter::class)
            ->args([
                [],
            ])

        ->set('doctrine.database_create_command', CreateDatabaseDoctrineCommand::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('console.command', ['command' => 'doctrine:database:create'])

        ->set('doctrine.database_drop_command', DropDatabaseDoctrineCommand::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('console.command', ['command' => 'doctrine:database:drop'])

        ->set('doctrine.query_sql_command', RunSqlDoctrineCommand::class)
            ->args([
                service(ManagerRegistryAwareConnectionProvider::class)->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'doctrine:query:sql'])

        ->set(RunSqlCommand::class)
            ->args([
                service(ManagerRegistryAwareConnectionProvider::class)->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'dbal:run-sql'])

        ->set(ProfilerController::class)
            ->args([
                service('twig'),
                service('doctrine'),
                service('profiler'),
            ])
            ->tag('controller.service_arguments')

        ->set('doctrine.dbal.idle_connection_listener', Listener::class)
            ->args([
                service('doctrine.dbal.connection_expiries'),
                service('service_container'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('doctrine.dbal.default_schema_manager_factory', DefaultSchemaManagerFactory::class)

        ->set('doctrine.dbal.legacy_schema_manager_factory', LegacySchemaManagerFactory::class);
};
