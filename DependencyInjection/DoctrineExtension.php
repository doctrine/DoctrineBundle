<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\ORM\Version;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\Config\FileLocator;
use Doctrine\Bundle\DoctrineCacheBundle\DependencyInjection\SymfonyBridgeAdapter;
use Doctrine\Bundle\DoctrineCacheBundle\DependencyInjection\CacheProviderLoader;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @author Kinn Coelho Julião <kinncj@php.net>
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    /**
     * @var string
     */
    private $defaultConnection;

    /**
     * @var array
     */
    private $entityManagers;

    /**
     * @var SymfonyBridgeAdapter
     */
    private $adapter;

    /**
     * @param SymfonyBridgeAdapter $adapter
     */
    public function __construct(SymfonyBridgeAdapter $adapter = null)
    {
        $this->adapter = $adapter ?: new SymfonyBridgeAdapter(new CacheProviderLoader(), 'doctrine.orm', 'orm');
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $this->adapter->loadServicesConfiguration($container);

        if (!empty($config['dbal'])) {
            $this->dbalLoad($config['dbal'], $container);
        }

        if (!empty($config['orm'])) {
            if (empty($config['dbal'])) {
                throw new \LogicException('Configuring the ORM layer requires to configure the DBAL layer as well.');
            }

            if (!class_exists('Doctrine\ORM\Version')) {
                throw new \LogicException('To configure the ORM layer, you must first install the doctrine/orm package.');
            }

            $this->ormLoad($config['orm'], $container);
        }
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
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('dbal.xml');

        if (empty($config['default_connection'])) {
            $keys = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $this->defaultConnection = $config['default_connection'];

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $this->defaultConnection));
        $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $this->defaultConnection), false));

        $container->setParameter('doctrine.dbal.connection_factory.types', $config['types']);

        $connections = array();

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
        // configuration
        $defitionClassname = $this->getDefinitionClassname();

        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new $defitionClassname('doctrine.dbal.connection.configuration'));
        $logger = null;
        if ($connection['logging']) {
            $logger = new Reference('doctrine.dbal.logger');
        }
        unset ($connection['logging']);
        if ($connection['profiling']) {
            $profilingLoggerId = 'doctrine.dbal.logger.profiling.'.$name;
            $container->setDefinition($profilingLoggerId, new $defitionClassname('doctrine.dbal.logger.profiling'));
            $profilingLogger = new Reference($profilingLoggerId);
            $container->getDefinition('data_collector.doctrine')->addMethodCall('addLogger', array($name, $profilingLogger));

            if (null !== $logger) {
                $chainLogger = new $defitionClassname('doctrine.dbal.logger.chain');
                $chainLogger->addMethodCall('addLogger', array($profilingLogger));

                $loggerId = 'doctrine.dbal.logger.chain.'.$name;
                $container->setDefinition($loggerId, $chainLogger);
                $logger = new Reference($loggerId);
            } else {
                $logger = $profilingLogger;
            }
        }
        unset($connection['profiling']);

        if (isset($connection['auto_commit'])) {
            $configuration->addMethodCall('setAutoCommit', array($connection['auto_commit']));
        }

        unset($connection['auto_commit']);

        if (isset($connection['schema_filter']) && $connection['schema_filter']) {
            $configuration->addMethodCall('setFilterSchemaAssetsExpression', array($connection['schema_filter']));
        }

        unset($connection['schema_filter']);

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', array($logger));
        }

        // event manager
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new $defitionClassname('doctrine.dbal.connection.event_manager'));

        // connection
        $options = $this->getConnectionOptions($connection);

        $def = $container
            ->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), new $defitionClassname('doctrine.dbal.connection'))
            ->setArguments(array(
                $options,
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name)),
                $connection['mapping_types'],
            ))
        ;

        // Set class in case "wrapper_class" option was used to assist IDEs
        if (isset($options['wrapperClass'])) {
            $def->setClass($options['wrapperClass']);
        }

        if (!empty($connection['use_savepoints'])) {
            $def->addMethodCall('setNestTransactionsWithSavepoints', array($connection['use_savepoints']));
        }

        // Create a shard_manager for this connection
        if (isset($options['shards'])) {
            $shardManagerDefinition = new Definition($options['shardManagerClass'], array(
                new Reference(sprintf('doctrine.dbal.%s_connection', $name))
            ));
            $container->setDefinition(sprintf('doctrine.dbal.%s_shard_manager', $name), $shardManagerDefinition);
        }
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

        foreach (array(
            'options' => 'driverOptions',
            'driver_class' => 'driverClass',
            'wrapper_class' => 'wrapperClass',
            'keep_slave' => 'keepSlave',
            'shard_choser' => 'shardChoser',
            'shard_manager_class' => 'shardManagerClass',
            'server_version' => 'serverVersion',
            'default_table_options' => 'defaultTableOptions',
        ) as $old => $new) {
            if (isset($options[$old])) {
                $options[$new] = $options[$old];
                unset($options[$old]);
            }
        }

        if (!empty($options['slaves']) && !empty($options['shards'])) {
            throw new InvalidArgumentException('Sharding and master-slave connection cannot be used together');
        }

        if (!empty($options['slaves'])) {
            $nonRewrittenKeys = array(
                'driver' => true, 'driverOptions' => true, 'driverClass' => true,
                'wrapperClass' => true, 'keepSlave' => true, 'shardChoser' => true,
                'platform' => true, 'slaves' => true, 'master' => true, 'shards' => true,
                'serverVersion' => true,
                // included by safety but should have been unset already
                'logging' => true, 'profiling' => true, 'mapping_types' => true, 'platform_service' => true,
            );
            foreach ($options as $key => $value) {
                if (isset($nonRewrittenKeys[$key])) {
                    continue;
                }
                $options['master'][$key] = $value;
                unset($options[$key]);
            }
            if (empty($options['wrapperClass'])) {
                // Change the wrapper class only if the user does not already forced using a custom one.
                $options['wrapperClass'] = 'Doctrine\\DBAL\\Connections\\MasterSlaveConnection';
            }
        } else {
            unset($options['slaves']);
        }

        if (!empty($options['shards'])) {
            $nonRewrittenKeys = array(
                'driver' => true, 'driverOptions' => true, 'driverClass' => true,
                'wrapperClass' => true, 'keepSlave' => true, 'shardChoser' => true,
                'platform' => true, 'slaves' => true, 'global' => true, 'shards' => true,
                'serverVersion' => true,
                // included by safety but should have been unset already
                'logging' => true, 'profiling' => true, 'mapping_types' => true, 'platform_service' => true,
            );
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
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('orm.xml');

        $this->entityManagers = array();
        foreach (array_keys($config['entity_managers']) as $name) {
            $this->entityManagers[$name] = sprintf('doctrine.orm.%s_entity_manager', $name);
        }
        $container->setParameter('doctrine.entity_managers', $this->entityManagers);

        if (empty($config['default_entity_manager'])) {
            $tmp = array_keys($this->entityManagers);
            $config['default_entity_manager'] = reset($tmp);
        }
        $container->setParameter('doctrine.default_entity_manager', $config['default_entity_manager']);

        $options = array('auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace');
        foreach ($options as $key) {
            $container->setParameter('doctrine.orm.'.$key, $config[$key]);
        }

        $container->setAlias('doctrine.orm.entity_manager', sprintf('doctrine.orm.%s_entity_manager', $config['default_entity_manager']));

        $config['entity_managers'] = $this->fixManagersAutoMappings($config['entity_managers'], $container->getParameter('kernel.bundles'));

        $loadPropertyInfoExtractor = interface_exists('Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface')
            && class_exists('Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor');

        foreach ($config['entity_managers'] as $name => $entityManager) {
            $entityManager['name'] = $name;
            $this->loadOrmEntityManager($entityManager, $container);

            if ($loadPropertyInfoExtractor) {
                $this->loadPropertyInfoExtractor($name, $container);
            }
        }

        if ($config['resolve_target_entities']) {
            $def = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
            foreach ($config['resolve_target_entities'] as $name => $implementation) {
                $def->addMethodCall('addResolveTargetEntity', array(
                    $name, $implementation, array(),
                ));
            }

            // BC: ResolveTargetEntityListener implements the subscriber interface since
            // v2.5.0-beta1 (Commit 437f812)
            if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
                $def->addTag('doctrine.event_listener', array('event' => 'loadClassMetadata'));
            } else {
                $def->addTag('doctrine.event_subscriber');
            }
        }
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $definitionClassname = $this->getDefinitionClassname();
        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new $definitionClassname('doctrine.orm.configuration'));

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        if (isset($entityManager['entity_listener_resolver']) && $entityManager['entity_listener_resolver']) {
            $container->setAlias(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $entityManager['entity_listener_resolver']);
        } else {
            $definition = new Definition('%doctrine.orm.entity_listener_resolver.class%');
            $definition->addArgument(new Reference('service_container'));
            $container->setDefinition(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $definition);
        }

        $methods = array(
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl' => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl' => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.orm.'.$entityManager['name'].'_metadata_driver'),
            'setProxyDir' => '%doctrine.orm.proxy_dir%',
            'setProxyNamespace' => '%doctrine.orm.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.orm.auto_generate_proxy_classes%',
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
        );
        // check for version to keep BC
        if (version_compare(Version::VERSION, "2.3.0-DEV") >= 0) {
            $methods = array_merge($methods, array(
                'setNamingStrategy' => new Reference($entityManager['naming_strategy']),
                'setQuoteStrategy' => new Reference($entityManager['quote_strategy']),
            ));
        }

        if (version_compare(Version::VERSION, "2.4.0-DEV") >= 0) {
            $methods = array_merge($methods, array(
                'setEntityListenerResolver' => new Reference(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name'])),
            ));
        }

        if (version_compare(Version::VERSION, "2.5.0-DEV") >= 0) {
            $listenerId = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $entityManager['name']);
            $listenerDef = $container->setDefinition($listenerId, new Definition('%doctrine.orm.listeners.attach_entity_listeners.class%'));
            $listenerTagParams = array('event' => 'loadClassMetadata');
            if (isset($entityManager['connection'])) {
                $listenerTagParams['connection'] = $entityManager['connection'];
            }
            $listenerDef->addTag('doctrine.event_listener', $listenerTagParams);
        }

        if (isset($entityManager['second_level_cache'])) {
            $this->loadOrmSecondLevelCache($entityManager, $ormConfigDef, $container);
        }

        if ($entityManager['repository_factory']) {
            $methods['setRepositoryFactory'] = new Reference($entityManager['repository_factory']);
        }

        foreach ($methods as $method => $arg) {
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        foreach ($entityManager['hydrators'] as $name => $class) {
            $ormConfigDef->addMethodCall('addCustomHydrationMode', array($name, $class));
        }

        if (!empty($entityManager['dql'])) {
            foreach ($entityManager['dql']['string_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomStringFunction', array($name, $function));
            }
            foreach ($entityManager['dql']['numeric_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomNumericFunction', array($name, $function));
            }
            foreach ($entityManager['dql']['datetime_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomDatetimeFunction', array($name, $function));
            }
        }

        $enabledFilters = array();
        $filtersParameters = array();
        foreach ($entityManager['filters'] as $name => $filter) {
            $ormConfigDef->addMethodCall('addFilter', array($name, $filter['class']));
            if ($filter['enabled']) {
                $enabledFilters[] = $name;
            }
            if ($filter['parameters']) {
                $filtersParameters[$name] = $filter['parameters'];
            }
        }

        $managerConfiguratorName = sprintf('doctrine.orm.%s_manager_configurator', $entityManager['name']);
        $container
            ->setDefinition($managerConfiguratorName, new $definitionClassname('doctrine.orm.manager_configurator.abstract'))
            ->replaceArgument(0, $enabledFilters)
            ->replaceArgument(1, $filtersParameters)
        ;

        if (!isset($entityManager['connection'])) {
            $entityManager['connection'] = $this->defaultConnection;
        }

        $container
            ->setDefinition(sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']), new $definitionClassname('doctrine.orm.entity_manager.abstract'))
            ->setArguments(array(
                new Reference(sprintf('doctrine.dbal.%s_connection', $entityManager['connection'])),
                new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name'])),
            ))
            ->setConfigurator(array(new Reference($managerConfiguratorName), 'configure'))
        ;

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']), false)
        );

        if (isset($entityManager['entity_listeners'])) {
            if (!isset($listenerDef)) {
                throw new InvalidArgumentException('Entity listeners configuration requires doctrine-orm 2.5.0 or newer');
            }

            $entities = $entityManager['entity_listeners']['entities'];

            foreach ($entities as $entityListenerClass => $entity) {
                foreach ($entity['listeners'] as $listenerClass => $listener) {
                    foreach ($listener['events'] as $listenerEvent) {
                        $listenerEventName = $listenerEvent['type'];
                        $listenerMethod = $listenerEvent['method'];

                        $listenerDef->addMethodCall('addEntityListener', array(
                            $entityListenerClass, $listenerClass, $listenerEventName, $listenerMethod,
                        ));
                    }
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
     *             dir: %kernel.root_dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entities
     *             prefix: DoctrineExtensions\Entities\
     *             alias: DExt
     *
     * In the case of bundles everything is really optional (which leads to autodetection for this bundle) but
     * in the mappings key everything except alias is a required argument.
     *
     * @param array            $entityManager A configured ORM entity manager
     * @param Definition       $ormConfigDef  A Definition instance
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers = array();
        $this->aliasMap = array();

        $this->loadMappingInformation($entityManager, $container);
        $this->registerMappingDrivers($entityManager, $container);

        $ormConfigDef->addMethodCall('setEntityNamespaces', array($this->aliasMap));
    }

    /**
     * Loads an ORM second level cache bundle mapping information.
     *
     * @example
     *  entity_managers:
     *      default:
     *          second_level_cache:
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
     *
     * @param array            $entityManager A configured ORM entity manager
     * @param Definition       $ormConfigDef  A Definition instance
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmSecondLevelCache(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
            throw new \InvalidArgumentException('Second-level cache requires doctrine-orm 2.5.0 or newer');
        }

        $driverId = null;
        $enabled = $entityManager['second_level_cache']['enabled'];

        if (isset($entityManager['second_level_cache']['region_cache_driver'])) {
            $driverName = 'second_level_cache.region_cache_driver';
            $driverMap = $entityManager['second_level_cache']['region_cache_driver'];
            $driverId = $this->loadCacheDriver($driverName, $entityManager['name'], $driverMap, $container);
        }

        $configId = sprintf('doctrine.orm.%s_second_level_cache.cache_configuration', $entityManager['name']);
        $regionsId = sprintf('doctrine.orm.%s_second_level_cache.regions_configuration', $entityManager['name']);
        $driverId = $driverId ?: sprintf('doctrine.orm.%s_second_level_cache.region_cache_driver', $entityManager['name']);
        $configDef = $container->setDefinition($configId, new Definition('%doctrine.orm.second_level_cache.cache_configuration.class%'));
        $regionsDef = $container->setDefinition($regionsId, new Definition('%doctrine.orm.second_level_cache.regions_configuration.class%'));

        $slcFactoryId = sprintf('doctrine.orm.%s_second_level_cache.default_cache_factory', $entityManager['name']);
        $factoryClass = isset($entityManager['second_level_cache']['factory']) ? $entityManager['second_level_cache']['factory'] : '%doctrine.orm.second_level_cache.default_cache_factory.class%';

        $definition = new Definition($factoryClass, array(new Reference($regionsId), new Reference($driverId)));

        $slcFactoryDef = $container
            ->setDefinition($slcFactoryId, $definition);

        if (isset($entityManager['second_level_cache']['regions'])) {
            foreach ($entityManager['second_level_cache']['regions'] as $name => $region) {
                $regionRef = null;
                $regionType = $region['type'];

                if ($regionType === 'service') {
                    $regionId = sprintf('doctrine.orm.%s_second_level_cache.region.%s', $entityManager['name'], $name);
                    $regionRef = new Reference($region['service']);

                    $container->setAlias($regionId, new Alias($region['service'], false));
                }

                if ($regionType === 'default' || $regionType === 'filelock') {
                    $regionId = sprintf('doctrine.orm.%s_second_level_cache.region.%s', $entityManager['name'], $name);
                    $driverName = sprintf('second_level_cache.region.%s_driver', $name);
                    $driverMap = $region['cache_driver'];
                    $driverId = $this->loadCacheDriver($driverName, $entityManager['name'], $driverMap, $container);
                    $regionRef = new Reference($regionId);

                    $container
                        ->setDefinition($regionId, new Definition('%doctrine.orm.second_level_cache.default_region.class%'))
                        ->setArguments(array($name, new Reference($driverId), $region['lifetime']));
                }

                if ($regionType === 'filelock') {
                    $regionId = sprintf('doctrine.orm.%s_second_level_cache.region.%s_filelock', $entityManager['name'], $name);

                    $container
                        ->setDefinition($regionId, new Definition('%doctrine.orm.second_level_cache.filelock_region.class%'))
                        ->setArguments(array($regionRef, $region['lock_path'], $region['lock_lifetime']));

                    $regionRef = new Reference($regionId);
                    $regionsDef->addMethodCall('getLockLifetime', array($name, $region['lock_lifetime']));
                }

                $regionsDef->addMethodCall('setLifetime', array($name, $region['lifetime']));
                $slcFactoryDef->addMethodCall('setRegion', array($regionRef));
            }
        }

        if ($entityManager['second_level_cache']['log_enabled']) {
            $loggerChainId = sprintf('doctrine.orm.%s_second_level_cache.logger_chain', $entityManager['name']);
            $loggerStatsId = sprintf('doctrine.orm.%s_second_level_cache.logger_statistics', $entityManager['name']);
            $loggerChaingDef = $container->setDefinition($loggerChainId, new Definition('%doctrine.orm.second_level_cache.logger_chain.class%'));
            $loggerStatsDef = $container->setDefinition($loggerStatsId, new Definition('%doctrine.orm.second_level_cache.logger_statistics.class%'));

            $loggerChaingDef->addMethodCall('setLogger', array('statistics', $loggerStatsDef));
            $configDef->addMethodCall('setCacheLogger', array($loggerChaingDef));

            foreach ($entityManager['second_level_cache']['loggers'] as $name => $logger) {
                $loggerId = sprintf('doctrine.orm.%s_second_level_cache.logger.%s', $entityManager['name'], $name);
                $loggerRef = new Reference($logger['service']);

                $container->setAlias($loggerId, new Alias($logger['service'], false));
                $loggerChaingDef->addMethodCall('setLogger', array($name, $loggerRef));
            }
        }

        $configDef->addMethodCall('setCacheFactory', array($slcFactoryDef));
        $configDef->addMethodCall('setRegionsConfiguration', array($regionsDef));
        $ormConfigDef->addMethodCall('setSecondLevelCacheEnabled', array($enabled));
        $ormConfigDef->addMethodCall('setSecondLevelCacheConfiguration', array($configDef));
    }

    /**
     * {@inheritDoc}
     */
    protected function getObjectManagerElementName($name)
    {
        return 'doctrine.orm.'.$name;
    }

    protected function getMappingObjectDefaultName()
    {
        return 'Entity';
    }

    /**
     * {@inheritDoc}
     */
    protected function getMappingResourceConfigDirectory()
    {
        return 'Resources/config/doctrine';
    }

    /**
     * {@inheritDoc}
     */
    protected function getMappingResourceExtension()
    {
        return 'orm';
    }

    /**
     * {@inheritDoc}
     */
    protected function loadCacheDriver($driverName, $entityManagerName, array $driverMap, ContainerBuilder $container)
    {
        if (!empty($driverMap['cache_provider'])) {
            $aliasId = $this->getObjectManagerElementName(sprintf('%s_%s', $entityManagerName, $driverName));
            $serviceId = sprintf('doctrine_cache.providers.%s', $driverMap['cache_provider']);

            $container->setAlias($aliasId, new Alias($serviceId, false));

            return $aliasId;
        }

        return $this->adapter->loadCacheDriver($driverName, $entityManagerName, $driverMap, $container);
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
     *
     * @param string           $entityManagerName
     * @param ContainerBuilder $container
     */
    private function loadPropertyInfoExtractor($entityManagerName, ContainerBuilder $container)
    {
        $metadataFactoryService = sprintf('doctrine.orm.%s_entity_manager.metadata_factory', $entityManagerName);

        $metadataFactoryDefinition = $container->register($metadataFactoryService, 'Doctrine\Common\Persistence\Mapping\ClassMetadataFactory');
        $metadataFactoryDefinition->setFactory(array(
            new Reference(sprintf('doctrine.orm.%s_entity_manager', $entityManagerName)),
            'getMetadataFactory'
        ));
        $metadataFactoryDefinition->setPublic(false);

        $propertyExtractorDefinition = $container->register(sprintf('doctrine.orm.%s_entity_manager.property_info_extractor', $entityManagerName), 'Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor');
        $propertyExtractorDefinition->addArgument(new Reference($metadataFactoryService));
        $propertyExtractorDefinition->addTag('property_info.list_extractor', array('priority' => -1001));
        $propertyExtractorDefinition->addTag('property_info.type_extractor', array('priority' => -999));
    }

    /**
     * @param array            $objectManager
     * @param ContainerBuilder $container
     * @param string           $cacheName
     */
    public function loadObjectManagerCacheDriver(array $objectManager, ContainerBuilder $container, $cacheName)
    {
        $this->loadCacheDriver($cacheName, $objectManager['name'], $objectManager[$cacheName.'_driver'], $container);
    }

    /**
     * {@inheritDoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/doctrine';
    }

    /**
     * {@inheritDoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    /**
     * @return string
     */
    private function getDefinitionClassname(): string
    {
        return class_exists(ChildDefinition::class) ? ChildDefinition::class : DefinitionDecorator::class;
    }
}
