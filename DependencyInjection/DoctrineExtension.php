<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\SQLParserUtils;
use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\ORM\Proxy\Autoloader;
use Doctrine\ORM\UnitOfWork;
use LogicException;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Bridge\Doctrine\Messenger\DoctrineClearEntityManagerWorkerSubscriber;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaSubscriber;
use Symfony\Bridge\Doctrine\SchemaListener\PdoCacheAdapterDoctrineSchemaSubscriber;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

use function class_exists;
use function sprintf;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    /** @var string */
    private $defaultConnection;

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        if (! empty($config['dbal'])) {
            $this->dbalLoad($config['dbal'], $container);

            $this->loadMessengerServices($container);
        }

        if (empty($config['orm'])) {
            return;
        }

        if (empty($config['dbal'])) {
            throw new LogicException('Configuring the ORM layer requires to configure the DBAL layer as well.');
        }

        $this->ormLoad($config['orm'], $container);
    }

    /**
     * Loads the DBAL configuration.
     *
     * Usage example:
     *
     *      <doctrine:dbal id="myconn" dbname="sfweb" user="root" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function dbalLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('dbal.xml');
        $chainLogger = $container->getDefinition('doctrine.dbal.logger.chain');
        $logger      = new Reference('doctrine.dbal.logger');
        if (! method_exists(SQLParserUtils::class, 'getPositionalPlaceholderPositions') && method_exists(LoggerChain::class, 'addLogger')) {
            // doctrine/dbal < 2.10.0
            $chainLogger->addMethodCall('addLogger', [$logger]);
        } else {
            $chainLogger->addArgument([$logger]);
        }

        if (class_exists(ImportCommand::class)) {
            $container->register('doctrine.database_import_command', ImportDoctrineCommand::class)
                ->addTag('console.command', ['command' => 'doctrine:database:import']);
        }

        if (empty($config['default_connection'])) {
            $keys                         = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $this->defaultConnection = $config['default_connection'];

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $this->defaultConnection));
        $container->getAlias('database_connection')->setPublic(true);
        $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $this->defaultConnection), false));

        $container->setParameter('doctrine.dbal.connection_factory.types', $config['types']);

        $connections = [];

        foreach (array_keys($config['connections']) as $name) {
            $connections[$name] = sprintf('doctrine.dbal.%s_connection', $name);
        }

        $container->setParameter('doctrine.connections', $connections);
        $container->setParameter('doctrine.default_connection', $this->defaultConnection);

        foreach ($config['connections'] as $name => $connection) {
            $this->loadDbalConnection($name, $connection, $container);
        }
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param string           $name       The name of the connection
     * @param array            $connection A dbal connection configuration.
     * @param ContainerBuilder $container  A ContainerBuilder instance
     */
    protected function loadDbalConnection($name, array $connection, ContainerBuilder $container)
    {
        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new ChildDefinition('doctrine.dbal.connection.configuration'));
        $logger        = null;
        if ($connection['logging']) {
            $logger = new Reference('doctrine.dbal.logger');
        }

        unset($connection['logging']);

        if ($connection['profiling']) {
            $profilingAbstractId = $connection['profiling_collect_backtrace'] ?
                'doctrine.dbal.logger.backtrace' :
                'doctrine.dbal.logger.profiling';

            $profilingLoggerId = $profilingAbstractId . '.' . $name;
            $container->setDefinition($profilingLoggerId, new ChildDefinition($profilingAbstractId));
            $profilingLogger = new Reference($profilingLoggerId);
            $container->getDefinition('data_collector.doctrine')
                ->addMethodCall('addLogger', [$name, $profilingLogger])
                ->replaceArgument(1, $connection['profiling_collect_schema_errors']);

            if ($logger !== null) {
                $chainLogger = $container->register(
                    'doctrine.dbal.logger.chain',
                    LoggerChain::class
                );
                if (! method_exists(SQLParserUtils::class, 'getPositionalPlaceholderPositions') && method_exists(LoggerChain::class, 'addLogger')) {
                    // doctrine/dbal < 2.10.0
                    $chainLogger->addMethodCall('addLogger', [$profilingLogger]);
                } else {
                    $chainLogger->addArgument([$logger, $profilingLogger]);
                }

                $loggerId = 'doctrine.dbal.logger.chain.' . $name;
                $container->setDefinition($loggerId, $chainLogger);
                $logger = new Reference($loggerId);
            } else {
                $logger = $profilingLogger;
            }
        }

        unset(
            $connection['profiling'],
            $connection['profiling_collect_backtrace'],
            $connection['profiling_collect_schema_errors']
        );

        if (isset($connection['auto_commit'])) {
            $configuration->addMethodCall('setAutoCommit', [$connection['auto_commit']]);
        }

        unset($connection['auto_commit']);

        if (isset($connection['schema_filter']) && $connection['schema_filter']) {
            $definition = new Definition(RegexSchemaAssetFilter::class, [$connection['schema_filter']]);
            $definition->addTag('doctrine.dbal.schema_filter', ['connection' => $name]);
            $container->setDefinition(sprintf('doctrine.dbal.%s_regex_schema_filter', $name), $definition);
        }

        unset($connection['schema_filter']);

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', [$logger]);
        }

        // event manager
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new ChildDefinition('doctrine.dbal.connection.event_manager'));

        // connection
        $options = $this->getConnectionOptions($connection);

        $def = $container
            ->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), new ChildDefinition('doctrine.dbal.connection'))
            ->setPublic(true)
            ->setArguments([
                $options,
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name)),
                $connection['mapping_types'],
            ]);

        // Set class in case "wrapper_class" option was used to assist IDEs
        if (isset($options['wrapperClass'])) {
            $def->setClass($options['wrapperClass']);
        }

        if (! empty($connection['use_savepoints'])) {
            $def->addMethodCall('setNestTransactionsWithSavepoints', [$connection['use_savepoints']]);
        }

        // Create a shard_manager for this connection
        if (isset($options['shards'])) {
            $shardManagerDefinition = new Definition($options['shardManagerClass'], [new Reference(sprintf('doctrine.dbal.%s_connection', $name))]);
            $container->setDefinition(sprintf('doctrine.dbal.%s_shard_manager', $name), $shardManagerDefinition);
        }

        // dbal < 2.11 BC layer
        if (! interface_exists(ConnectionProvider::class)) {
            return;
        }

        $container->setDefinition(
            ManagerRegistryAwareConnectionProvider::class,
            new Definition(ManagerRegistryAwareConnectionProvider::class, [$container->getDefinition('doctrine')])
        );
    }

    protected function getConnectionOptions($connection)
    {
        $options = $connection;

        if (isset($options['platform_service'])) {
            $options['platform'] = new Reference($options['platform_service']);
            unset($options['platform_service']);
        }

        unset($options['mapping_types']);

        if (isset($options['shard_choser_service'])) {
            $options['shard_choser'] = new Reference($options['shard_choser_service']);
            unset($options['shard_choser_service']);
        }

        foreach (
            [
                'options' => 'driverOptions',
                'driver_class' => 'driverClass',
                'wrapper_class' => 'wrapperClass',
                'keep_slave' => class_exists(PrimaryReadReplicaConnection::class) ? 'keepReplica' : 'keepSlave',
                'keep_replica' => 'keepReplica',
                'replicas' => 'replica',
                'shard_choser' => 'shardChoser',
                'shard_manager_class' => 'shardManagerClass',
                'server_version' => 'serverVersion',
                'default_table_options' => 'defaultTableOptions',
            ] as $old => $new
        ) {
            if (! isset($options[$old])) {
                continue;
            }

            $options[$new] = $options[$old];
            unset($options[$old]);
        }

        if (! empty($options['slaves']) && ! empty($options['replica']) && ! empty($options['shards'])) {
            throw new InvalidArgumentException('Sharding and master-slave connection cannot be used together');
        }

        if (! empty($options['slaves']) || ! empty($options['replica'])) {
            $nonRewrittenKeys = [
                'driver' => true,
                'driverOptions' => true,
                'driverClass' => true,
                'wrapperClass' => true,
                'keepSlave' => true,
                'keepReplica' => true,
                'shardChoser' => true,
                'platform' => true,
                'slaves' => true,
                'master' => true,
                'primary' => true,
                'replica' => true,
                'shards' => true,
                'serverVersion' => true,
                'defaultTableOptions' => true,
                // included by safety but should have been unset already
                'logging' => true,
                'profiling' => true,
                'mapping_types' => true,
                'platform_service' => true,
            ];
            foreach ($options as $key => $value) {
                if (isset($nonRewrittenKeys[$key])) {
                    continue;
                }

                $options[class_exists(PrimaryReadReplicaConnection::class) ? 'primary' : 'master'][$key] = $value;
                unset($options[$key]);
            }

            if (empty($options['wrapperClass'])) {
                // Change the wrapper class only if user did not configure custom one.
                $options['wrapperClass'] = class_exists(PrimaryReadReplicaConnection::class) ?
                    PrimaryReadReplicaConnection::class : // dbal >= 2.11
                    MasterSlaveConnection::class; // dbal < 2.11
            }
        } else {
            unset($options['slaves'], $options['replica']);
        }

        if (! empty($options['shards'])) {
            $nonRewrittenKeys = [
                'driver' => true,
                'driverOptions' => true,
                'driverClass' => true,
                'wrapperClass' => true,
                'keepSlave' => true,
                'keepReplica' => true,
                'shardChoser' => true,
                'platform' => true,
                'slaves' => true,
                'replica' => true,
                'global' => true,
                'shards' => true,
                'serverVersion' => true,
                'defaultTableOptions' => true,
                // included by safety but should have been unset already
                'logging' => true,
                'profiling' => true,
                'mapping_types' => true,
                'platform_service' => true,
            ];
            foreach ($options as $key => $value) {
                if (isset($nonRewrittenKeys[$key])) {
                    continue;
                }

                $options['global'][$key] = $value;
                unset($options[$key]);
            }

            if (empty($options['wrapperClass'])) {
                // Change the wrapper class only if the user does not already forced using a custom one.
                $options['wrapperClass'] = 'Doctrine\\DBAL\\Sharding\\PoolingShardConnection';
            }

            if (empty($options['shardManagerClass'])) {
                // Change the shard manager class only if the user does not already forced using a custom one.
                $options['shardManagerClass'] = 'Doctrine\\DBAL\\Sharding\\PoolingShardManager';
            }
        } else {
            unset($options['shards']);
        }

        return $options;
    }

    /**
     * Loads the Doctrine ORM configuration.
     *
     * Usage example:
     *
     *     <doctrine:orm id="mydm" connection="myconn" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function ormLoad(array $config, ContainerBuilder $container)
    {
        if (! class_exists(UnitOfWork::class)) {
            throw new LogicException('To configure the ORM layer, you must first install the doctrine/orm package.');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('orm.xml');

        if (class_exists(AbstractType::class)) {
            $container->getDefinition('form.type.entity')->addTag('kernel.reset', ['method' => 'reset']);
        }

        // available in Symfony 5.1 and higher
        if (! class_exists(PdoCacheAdapterDoctrineSchemaSubscriber::class)) {
            $container->removeDefinition('doctrine.orm.listeners.pdo_cache_adapter_doctrine_schema_subscriber');
        }

        $entityManagers = [];
        foreach (array_keys($config['entity_managers']) as $name) {
            $entityManagers[$name] = sprintf('doctrine.orm.%s_entity_manager', $name);
        }

        $container->setParameter('doctrine.entity_managers', $entityManagers);

        if (empty($config['default_entity_manager'])) {
            $tmp                              = array_keys($entityManagers);
            $config['default_entity_manager'] = reset($tmp);
        }

        $container->setParameter('doctrine.default_entity_manager', $config['default_entity_manager']);

        $options = ['auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace'];
        foreach ($options as $key) {
            $container->setParameter('doctrine.orm.' . $key, $config[$key]);
        }

        $container->setAlias('doctrine.orm.entity_manager', $defaultEntityManagerDefinitionId = sprintf('doctrine.orm.%s_entity_manager', $config['default_entity_manager']));
        $container->getAlias('doctrine.orm.entity_manager')->setPublic(true);

        $config['entity_managers'] = $this->fixManagersAutoMappings($config['entity_managers'], $container->getParameter('kernel.bundles'));

        foreach ($config['entity_managers'] as $name => $entityManager) {
            $entityManager['name'] = $name;
            $this->loadOrmEntityManager($entityManager, $container);

            if (interface_exists(PropertyInfoExtractorInterface::class)) {
                $this->loadPropertyInfoExtractor($name, $container);
            }

            $this->loadValidatorLoader($name, $container);
        }

        if ($config['resolve_target_entities']) {
            $def = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
            foreach ($config['resolve_target_entities'] as $name => $implementation) {
                $def->addMethodCall('addResolveTargetEntity', [
                    $name,
                    $implementation,
                    [],
                ]);
            }

            $def->addTag('doctrine.event_subscriber');
        }

        $container->registerForAutoconfiguration(ServiceEntityRepositoryInterface::class)
            ->addTag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('doctrine.event_subscriber');

        /**
         * @see DoctrineBundle::boot()
         */
        $container->getDefinition($defaultEntityManagerDefinitionId)
            ->addTag('container.preload', [
                'class' => Autoloader::class,
            ]);
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new ChildDefinition('doctrine.orm.configuration'));

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        if (isset($entityManager['entity_listener_resolver']) && $entityManager['entity_listener_resolver']) {
            $container->setAlias(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $entityManager['entity_listener_resolver']);
        } else {
            $definition = new Definition('%doctrine.orm.entity_listener_resolver.class%');
            $definition->addArgument(new Reference('service_container'));
            $container->setDefinition(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $definition);
        }

        $methods = [
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl' => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl' => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.orm.' . $entityManager['name'] . '_metadata_driver'),
            'setProxyDir' => '%doctrine.orm.proxy_dir%',
            'setProxyNamespace' => '%doctrine.orm.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.orm.auto_generate_proxy_classes%',
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
            'setNamingStrategy' => new Reference($entityManager['naming_strategy']),
            'setQuoteStrategy' => new Reference($entityManager['quote_strategy']),
            'setEntityListenerResolver' => new Reference(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name'])),
        ];

        $listenerId        = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $entityManager['name']);
        $listenerDef       = $container->setDefinition($listenerId, new Definition('%doctrine.orm.listeners.attach_entity_listeners.class%'));
        $listenerTagParams = ['event' => 'loadClassMetadata'];
        if (isset($entityManager['connection'])) {
            $listenerTagParams['connection'] = $entityManager['connection'];
        }

        $listenerDef->addTag('doctrine.event_listener', $listenerTagParams);

        if (isset($entityManager['second_level_cache'])) {
            $this->loadOrmSecondLevelCache($entityManager, $ormConfigDef, $container);
        }

        if ($entityManager['repository_factory']) {
            $methods['setRepositoryFactory'] = new Reference($entityManager['repository_factory']);
        }

        foreach ($methods as $method => $arg) {
            $ormConfigDef->addMethodCall($method, [$arg]);
        }

        foreach ($entityManager['hydrators'] as $name => $class) {
            $ormConfigDef->addMethodCall('addCustomHydrationMode', [$name, $class]);
        }

        if (! empty($entityManager['dql'])) {
            foreach ($entityManager['dql']['string_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomStringFunction', [$name, $function]);
            }

            foreach ($entityManager['dql']['numeric_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomNumericFunction', [$name, $function]);
            }

            foreach ($entityManager['dql']['datetime_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomDatetimeFunction', [$name, $function]);
            }
        }

        $enabledFilters    = [];
        $filtersParameters = [];
        foreach ($entityManager['filters'] as $name => $filter) {
            $ormConfigDef->addMethodCall('addFilter', [$name, $filter['class']]);
            if ($filter['enabled']) {
                $enabledFilters[] = $name;
            }

            if (! $filter['parameters']) {
                continue;
            }

            $filtersParameters[$name] = $filter['parameters'];
        }

        $managerConfiguratorName = sprintf('doctrine.orm.%s_manager_configurator', $entityManager['name']);
        $container
            ->setDefinition($managerConfiguratorName, new ChildDefinition('doctrine.orm.manager_configurator.abstract'))
            ->replaceArgument(0, $enabledFilters)
            ->replaceArgument(1, $filtersParameters);

        if (! isset($entityManager['connection'])) {
            $entityManager['connection'] = $this->defaultConnection;
        }

        $container
            ->setDefinition(sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']), new ChildDefinition('doctrine.orm.entity_manager.abstract'))
            ->setPublic(true)
            ->setArguments([
                new Reference(sprintf('doctrine.dbal.%s_connection', $entityManager['connection'])),
                new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name'])),
            ])
            ->setConfigurator([new Reference($managerConfiguratorName), 'configure']);

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']), false)
        );

        if (! isset($entityManager['entity_listeners'])) {
            return;
        }

        if (! isset($listenerDef)) {
            throw new InvalidArgumentException('Entity listeners configuration requires doctrine-orm 2.5.0 or newer');
        }

        $entities = $entityManager['entity_listeners']['entities'];

        foreach ($entities as $entityListenerClass => $entity) {
            foreach ($entity['listeners'] as $listenerClass => $listener) {
                foreach ($listener['events'] as $listenerEvent) {
                    $listenerEventName = $listenerEvent['type'];
                    $listenerMethod    = $listenerEvent['method'];

                    $listenerDef->addMethodCall('addEntityListener', [
                        $entityListenerClass,
                        $listenerClass,
                        $listenerEventName,
                        $listenerMethod,
                    ]);
                }
            }
        }
    }

    /**
     * Loads an ORM entity managers bundle mapping information.
     *
     * There are two distinct configuration possibilities for mapping information:
     *
     * 1. Specify a bundle and optionally details where the entity and mapping information reside.
     * 2. Specify an arbitrary mapping location.
     *
     * @param array            $entityManager A configured ORM entity manager
     * @param Definition       $ormConfigDef  A Definition instance
     * @param ContainerBuilder $container     A ContainerBuilder instance
     *
     * @example
     *
     *  doctrine.orm:
     *     mappings:
     *         MyBundle1: ~
     *         MyBundle2: yml
     *         MyBundle3: { type: annotation, dir: Entities/ }
     *         MyBundle4: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle5:
     *             type: yml
     *             dir: bundle-mappings/
     *             alias: BundleAlias
     *         arbitrary_key:
     *             type: xml
     *             dir: %kernel.project_dir%/src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entities
     *             prefix: DoctrineExtensions\Entities\
     *             alias: DExt
     *
     * In the case of bundles everything is really optional (which leads to autodetection for this bundle) but
     * in the mappings key everything except alias is a required argument.
     */
    protected function loadOrmEntityManagerMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers  = [];
        $this->aliasMap = [];

        $this->loadMappingInformation($entityManager, $container);
        $this->registerMappingDrivers($entityManager, $container);

        $ormConfigDef->addMethodCall('setEntityNamespaces', [$this->aliasMap]);
    }

    /**
     * Loads an ORM second level cache bundle mapping information.
     *
     * @param array            $entityManager A configured ORM entity manager
     * @param Definition       $ormConfigDef  A Definition instance
     * @param ContainerBuilder $container     A ContainerBuilder instance
     *
     * @example
     *  entity_managers:
     *      default:
     *          second_level_cache:
     *              region_lifetime: 3600
     *              region_lock_lifetime: 60
     *              region_cache_driver: apc
     *              log_enabled: true
     *              regions:
     *                  my_service_region:
     *                      type: service
     *                      service : "my_service_region"
     *
     *                  my_query_region:
     *                      lifetime: 300
     *                      cache_driver: array
     *                      type: filelock
     *
     *                  my_entity_region:
     *                      lifetime: 600
     *                      cache_driver:
     *                          type: apc
     */
    protected function loadOrmSecondLevelCache(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        $driverId = null;
        $enabled  = $entityManager['second_level_cache']['enabled'];

        if (isset($entityManager['second_level_cache']['region_cache_driver'])) {
            $driverName = 'second_level_cache.region_cache_driver';
            $driverMap  = $entityManager['second_level_cache']['region_cache_driver'];
            $driverId   = $this->loadCacheDriver($driverName, $entityManager['name'], $driverMap, $container);
        }

        $configId   = sprintf('doctrine.orm.%s_second_level_cache.cache_configuration', $entityManager['name']);
        $regionsId  = sprintf('doctrine.orm.%s_second_level_cache.regions_configuration', $entityManager['name']);
        $driverId   = $driverId ?: sprintf('doctrine.orm.%s_second_level_cache.region_cache_driver', $entityManager['name']);
        $configDef  = $container->setDefinition($configId, new Definition('%doctrine.orm.second_level_cache.cache_configuration.class%'));
        $regionsDef = $container
            ->setDefinition($regionsId, new Definition('%doctrine.orm.second_level_cache.regions_configuration.class%'))
            ->setArguments([$entityManager['second_level_cache']['region_lifetime'], $entityManager['second_level_cache']['region_lock_lifetime']]);

        $slcFactoryId = sprintf('doctrine.orm.%s_second_level_cache.default_cache_factory', $entityManager['name']);
        $factoryClass = isset($entityManager['second_level_cache']['factory']) ? $entityManager['second_level_cache']['factory'] : '%doctrine.orm.second_level_cache.default_cache_factory.class%';

        $definition = new Definition($factoryClass, [new Reference($regionsId), new Reference($driverId)]);

        $slcFactoryDef = $container
            ->setDefinition($slcFactoryId, $definition);

        if (isset($entityManager['second_level_cache']['regions'])) {
            foreach ($entityManager['second_level_cache']['regions'] as $name => $region) {
                $regionRef  = null;
                $regionType = $region['type'];

                if ($regionType === 'service') {
                    $regionId  = sprintf('doctrine.orm.%s_second_level_cache.region.%s', $entityManager['name'], $name);
                    $regionRef = new Reference($region['service']);

                    $container->setAlias($regionId, new Alias($region['service'], false));
                }

                if ($regionType === 'default' || $regionType === 'filelock') {
                    $regionId   = sprintf('doctrine.orm.%s_second_level_cache.region.%s', $entityManager['name'], $name);
                    $driverName = sprintf('second_level_cache.region.%s_driver', $name);
                    $driverMap  = $region['cache_driver'];
                    $driverId   = $this->loadCacheDriver($driverName, $entityManager['name'], $driverMap, $container);
                    $regionRef  = new Reference($regionId);

                    $container
                        ->setDefinition($regionId, new Definition('%doctrine.orm.second_level_cache.default_region.class%'))
                        ->setArguments([$name, new Reference($driverId), $region['lifetime']]);
                }

                if ($regionType === 'filelock') {
                    $regionId = sprintf('doctrine.orm.%s_second_level_cache.region.%s_filelock', $entityManager['name'], $name);

                    $container
                        ->setDefinition($regionId, new Definition('%doctrine.orm.second_level_cache.filelock_region.class%'))
                        ->setArguments([$regionRef, $region['lock_path'], $region['lock_lifetime']]);

                    $regionRef = new Reference($regionId);
                    $regionsDef->addMethodCall('getLockLifetime', [$name, $region['lock_lifetime']]);
                }

                $regionsDef->addMethodCall('setLifetime', [$name, $region['lifetime']]);
                $slcFactoryDef->addMethodCall('setRegion', [$regionRef]);
            }
        }

        if ($entityManager['second_level_cache']['log_enabled']) {
            $loggerChainId   = sprintf('doctrine.orm.%s_second_level_cache.logger_chain', $entityManager['name']);
            $loggerStatsId   = sprintf('doctrine.orm.%s_second_level_cache.logger_statistics', $entityManager['name']);
            $loggerChaingDef = $container->setDefinition($loggerChainId, new Definition('%doctrine.orm.second_level_cache.logger_chain.class%'));
            $loggerStatsDef  = $container->setDefinition($loggerStatsId, new Definition('%doctrine.orm.second_level_cache.logger_statistics.class%'));

            $loggerChaingDef->addMethodCall('setLogger', ['statistics', $loggerStatsDef]);
            $configDef->addMethodCall('setCacheLogger', [$loggerChaingDef]);

            foreach ($entityManager['second_level_cache']['loggers'] as $name => $logger) {
                $loggerId  = sprintf('doctrine.orm.%s_second_level_cache.logger.%s', $entityManager['name'], $name);
                $loggerRef = new Reference($logger['service']);

                $container->setAlias($loggerId, new Alias($logger['service'], false));
                $loggerChaingDef->addMethodCall('setLogger', [$name, $loggerRef]);
            }
        }

        $configDef->addMethodCall('setCacheFactory', [$slcFactoryDef]);
        $configDef->addMethodCall('setRegionsConfiguration', [$regionsDef]);
        $ormConfigDef->addMethodCall('setSecondLevelCacheEnabled', [$enabled]);
        $ormConfigDef->addMethodCall('setSecondLevelCacheConfiguration', [$configDef]);
    }

    /**
     * {@inheritDoc}
     */
    protected function getObjectManagerElementName($name): string
    {
        return 'doctrine.orm.' . $name;
    }

    protected function getMappingObjectDefaultName(): string
    {
        return 'Entity';
    }

    protected function getMappingResourceConfigDirectory(): string
    {
        return 'Resources/config/doctrine';
    }

    protected function getMappingResourceExtension(): string
    {
        return 'orm';
    }

    /**
     * {@inheritDoc}
     */
    protected function loadCacheDriver($cacheName, $objectManagerName, array $cacheDriver, ContainerBuilder $container): string
    {
        $serviceId = null;
        $aliasId   = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, $cacheName));

        switch ($cacheDriver['type'] ?? 'pool') {
            case 'service':
                $serviceId = $cacheDriver['id'];
                break;

            case 'pool':
                $serviceId = $this->createPoolCacheDefinition($container, $cacheDriver['pool'] ?? $this->createArrayAdapterCachePool($container, $objectManagerName, $cacheName));
                break;

            default:
                throw new \InvalidArgumentException(sprintf(
                    'Unknown cache of type "%s" configured for cache "%s" in entity manager "%s".',
                    $cacheDriver['type'],
                    $cacheName,
                    $objectManagerName
                ));
        }

        $container->setAlias($aliasId, new Alias($serviceId, false));

        return $aliasId;
    }

    /**
     * Loads a configured entity managers cache drivers.
     *
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmCacheDrivers(array $entityManager, ContainerBuilder $container)
    {
        $this->loadCacheDriver('metadata_cache', $entityManager['name'], $entityManager['metadata_cache_driver'], $container);
        $this->loadCacheDriver('result_cache', $entityManager['name'], $entityManager['result_cache_driver'], $container);
        $this->loadCacheDriver('query_cache', $entityManager['name'], $entityManager['query_cache_driver'], $container);
    }

    /**
     * Loads a property info extractor for each defined entity manager.
     */
    private function loadPropertyInfoExtractor(string $entityManagerName, ContainerBuilder $container): void
    {
        $propertyExtractorDefinition = $container->register(sprintf('doctrine.orm.%s_entity_manager.property_info_extractor', $entityManagerName), DoctrineExtractor::class);
        $argumentId                  = sprintf('doctrine.orm.%s_entity_manager', $entityManagerName);

        $propertyExtractorDefinition->addArgument(new Reference($argumentId));

        $propertyExtractorDefinition->addTag('property_info.list_extractor', ['priority' => -1001]);
        $propertyExtractorDefinition->addTag('property_info.type_extractor', ['priority' => -999]);
        $propertyExtractorDefinition->addTag('property_info.access_extractor', ['priority' => -999]);
    }

    /**
     * Loads a validator loader for each defined entity manager.
     */
    private function loadValidatorLoader(string $entityManagerName, ContainerBuilder $container): void
    {
        $validatorLoaderDefinition = $container->register(sprintf('doctrine.orm.%s_entity_manager.validator_loader', $entityManagerName), DoctrineLoader::class);
        $validatorLoaderDefinition->addArgument(new Reference(sprintf('doctrine.orm.%s_entity_manager', $entityManagerName)));

        $validatorLoaderDefinition->addTag('validator.auto_mapper', ['priority' => -100]);
    }

    /**
     * @param array  $objectManager
     * @param string $cacheName
     */
    public function loadObjectManagerCacheDriver(array $objectManager, ContainerBuilder $container, $cacheName)
    {
        $this->loadCacheDriver($cacheName, $objectManager['name'], $objectManager[$cacheName . '_driver'], $container);
    }

    public function getXsdValidationBasePath(): string
    {
        return __DIR__ . '/../Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/doctrine';
    }

    /**
     * {@inheritDoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    protected function getMetadataDriverClass(string $driverType): string
    {
        return '%' . $this->getObjectManagerElementName('metadata.' . $driverType . '.class%');
    }

    private function loadMessengerServices(ContainerBuilder $container): void
    {
        // If the Messenger component is installed and the doctrine transaction middleware is available, wire it:
        if (! interface_exists(MessageBusInterface::class) || ! class_exists(DoctrineTransactionMiddleware::class)) {
            return;
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('messenger.xml');

        if (! class_exists(DoctrineClearEntityManagerWorkerSubscriber::class)) {
            $container->removeDefinition('doctrine.orm.messenger.event_subscriber.doctrine_clear_entity_manager');
        }

        // available in Symfony 5.1 and higher
        if (! class_exists(MessengerTransportDoctrineSchemaSubscriber::class)) {
            $container->removeDefinition('doctrine.orm.messenger.doctrine_schema_subscriber');
        }

        $transportFactoryDefinition = $container->getDefinition('messenger.transport.doctrine.factory');
        if (! class_exists(DoctrineTransportFactory::class)) {
            // If symfony/messenger < 5.1
            if (! class_exists(\Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory::class)) {
                // Dont add the tag
                return;
            }

            $transportFactoryDefinition->setClass(\Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransportFactory::class);
        }

        $transportFactoryDefinition->addTag('messenger.transport_factory');
    }

    private function createPoolCacheDefinition(ContainerBuilder $container, string $poolName): string
    {
        $serviceId = sprintf('doctrine.orm.cache.provider.%s', $poolName);

        $definition = $container->register($serviceId, DoctrineProvider::class);
        $definition->addArgument(new Reference($poolName));

        return $serviceId;
    }

    private function createArrayAdapterCachePool(ContainerBuilder $container, string $objectManagerName, string $cacheName): string
    {
        $id = sprintf('cache.doctrine.orm.%s.%s', $objectManagerName, str_replace('_cache', '', $cacheName));

        $poolDefinition = $container->register($id, ArrayAdapter::class);
        $poolDefinition->addTag('cache.pool');
        $container->setDefinition($id, $poolDefinition);

        return $id;
    }
}
