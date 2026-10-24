<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Controller\ProfilerController;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\Bundle\DoctrineBundle\Dbal\SchemaAssetsFilterManager;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Listener;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('doctrine.entity_managers', [])
        ->set('doctrine.default_entity_manager', '');

    $container->services()

        ->alias(Connection::class, 'database_connection')
        ->alias(ManagerRegistry::class, 'doctrine')

        ->set('data_collector.doctrine', DoctrineDataCollector::class)
            ->args([
                service('doctrine'),
                true,
                service('doctrine.debug_data_holder')->nullOnInvalid(),
            ])
            ->tag('data_collector', ['template' => '@Doctrine/Collector/db.html.twig', 'id' => 'db', 'priority' => 250])

        ->set('doctrine.dbal.connection_factory', ConnectionFactory::class)
            ->args([
                (string) param('doctrine.dbal.connection_factory.types'),
                service('doctrine.dbal.connection_factory.dsn_parser'),
            ])

        ->set('doctrine.dbal.connection_factory.dsn_parser', DsnParser::class)
            ->args([
                [],
            ])

        ->set('doctrine.dbal.connection', Connection::class)
            ->abstract()
            ->factory([service('doctrine.dbal.connection_factory'), 'createConnection'])

        ->set('doctrine.dbal.connection.event_manager', ContainerAwareEventManager::class)
            ->abstract()
            ->args([
                service('service_container'),
            ])

        ->set('doctrine.dbal.connection.configuration', Configuration::class)
            ->abstract()

        ->set('doctrine', Registry::class)
            ->public()
            ->args([
                service('service_container'),
                (string) param('doctrine.connections'),
                (string) param('doctrine.entity_managers'),
                (string) param('doctrine.default_connection'),
                (string) param('doctrine.default_entity_manager'),
            ])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('doctrine.twig.doctrine_extension', DoctrineExtension::class)
            ->tag('twig.extension')

        ->set('doctrine.dbal.schema_asset_filter_manager', SchemaAssetsFilterManager::class)
            ->abstract()

        ->set('doctrine.manager_registry_aware_connection_provider', ManagerRegistryAwareConnectionProvider::class)
            ->args([
                service('doctrine'),
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

        ->set('doctrine.run_sql_command', RunSqlCommand::class)
            ->args([
                service('doctrine.manager_registry_aware_connection_provider'),
            ])
            ->tag('console.command', ['command' => 'dbal:run-sql'])

        ->set('doctrine.profiler_controller', ProfilerController::class)
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

        ->set('doctrine.dbal.default_schema_manager_factory', DefaultSchemaManagerFactory::class);
};
