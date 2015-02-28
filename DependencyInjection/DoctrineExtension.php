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

            $this->ormLoad($config['orm'], $container);
        }

        $this->addClassesToCompile(array(
            'Doctrine\\Common\\Annotations\\DocLexer',
            'Doctrine\\Common\\Annotations\\FileCacheReader',
            'Doctrine\\Common\\Annotations\\PhpParser',
            'Doctrine\\Common\\Annotations\\Reader',
            'Doctrine\\Common\\Lexer',
            'Doctrine\\Common\\Persistence\\ConnectionRegistry',
            'Doctrine\\Common\\Persistence\\Proxy',
            'Doctrine\\Common\\Util\\ClassUtils',
            'Doctrine\\Bundle\\DoctrineBundle\\Registry',
        ));
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

        $def = $container->getDefinition('doctrine.dbal.connection');
        if (method_exists($def, 'setFactory')) {
            // to be inlined in dbal.xml when dependency on Symfony DependencyInjection is bumped to 2.6
            $def->setFactory(array(new Reference('doctrine.dbal.connection_factory'), 'createConnection'));
        } else {
            // to be removed when dependency on Symfony DependencyInjection is bumped to 2.6
            $def->setFactoryService('doctrine.dbal.connection_factory');
            $def->setFactoryMethod('createConnection');
        }

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
        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new DefinitionDecorator('doctrine.dbal.connection.configuration'));
        $logger = null;
        if ($connection['logging']) {
            $logger = new Reference('doctrine.dbal.logger');
        }
        unset ($connection['logging']);
        if ($connection['profiling']) {
            $profilingLoggerId = 'doctrine.dbal.logger.profiling.'.$name;
            $container->setDefinition($profilingLoggerId, new DefinitionDecorator('doctrine.dbal.logger.profiling'));
            $logger = new Reference($profilingLoggerId);
            $container->getDefinition('data_collector.doctrine')->addMethodCall('addLogger', array($name, $logger));

            if (null !== $logger) {
                $chainLogger = new DefinitionDecorator('doctrine.dbal.logger.chain');
                $chainLogger->addMethodCall('addLogger', array($logger));

                $loggerId = 'doctrine.dbal.logger.chain.'.$name;
                $container->setDefinition($loggerId, $chainLogger);
                $logger = new Reference($loggerId);
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
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new DefinitionDecorator('doctrine.dbal.connection.event_manager'));

        // connection
        // PDO ignores the charset property before 5.3.6 so the init listener has to be used instead.
        if (isset($connection['charset']) && version_compare(PHP_VERSION, '5.3.6', '<')) {
            if ((isset($connection['driver']) && stripos($connection['driver'], 'mysql') !== false) ||
                 (isset($connection['driver_class']) && stripos($connection['driver_class'], 'mysql') !== false)) {
                $mysqlSessionInit = new Definition('%doctrine.dbal.events.mysql_session_init.class%');
                $mysqlSessionInit->setArguments(array($connection['charset']));
                $mysqlSessionInit->setPublic(false);
                $mysqlSessionInit->addTag('doctrine.event_subscriber', array('connection' => $name));

                $container->setDefinition(
                    sprintf('doctrine.dbal.%s_connection.events.mysqlsessioninit', $name),
                    $mysqlSessionInit
                );
                unset($connection['charset']);
            }
        }

        $options = $this->getConnectionOptions($connection);

        $container
            ->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), new DefinitionDecorator('doctrine.dbal.connection'))
            ->setArguments(array(
                $options,
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name)),
                $connection['mapping_types'],
            ))
        ;
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
            'server_version' => 'serverVersion',
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

        // BC logic to handle DoctrineBridge < 2.6
        if (!method_exists($this, 'fixManagersAutoMappings')) {
            foreach ($config['entity_managers'] as $entityManager) {
                if ($entityManager['auto_mapping'] && count($config['entity_managers']) > 1) {
                    throw new \LogicException('You cannot enable "auto_mapping" when several entity managers are defined.');
                }
            }
        } else {
            $config['entity_managers'] = $this->fixManagersAutoMappings($config['entity_managers'], $container->getParameter('kernel.bundles'));
        }

        $def = $container->getDefinition('doctrine.orm.entity_manager.abstract');
        if (method_exists($def, 'setFactory')) {
            // to be inlined in dbal.xml when dependency on Symfony DependencyInjection is bumped to 2.6
            $def->setFactory(array('%doctrine.orm.entity_manager.class%', 'create'));
        } else {
            // to be removed when dependency on Symfony DependencyInjection is bumped to 2.6
            $def->setFactoryClass('%doctrine.orm.entity_manager.class%');
            $def->setFactoryMethod('create');
        }

        foreach ($config['entity_managers'] as $name => $entityManager) {
            $entityManager['name'] = $name;
            $this->loadOrmEntityManager($entityManager, $container);
        }

        if ($config['resolve_target_entities']) {
            $def = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
            foreach ($config['resolve_target_entities'] as $name => $implementation) {
                $def->addMethodCall('addResolveTargetEntity', array(
                    $name, $implementation, array(),
                ));
            }

            $def->addTag('doctrine.event_listener', array('event' => 'loadClassMetadata'));
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
        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new DefinitionDecorator('doctrine.orm.configuration'));

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        if (isset($entityManager['entity_listener_resolver']) && $entityManager['entity_listener_resolver']) {
            $container->setAlias(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $entityManager['entity_listener_resolver']);
        } else {
            $container->setDefinition(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), new Definition('%doctrine.orm.entity_listener_resolver.class%'));
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
            ));
        }

        if (version_compare(Version::VERSION, "2.4.0-DEV") >= 0) {
            $methods = array_merge($methods, array(
                'setEntityListenerResolver' => new Reference(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name'])),
            ));
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
            ->setDefinition($managerConfiguratorName, new DefinitionDecorator('doctrine.orm.manager_configurator.abstract'))
            ->replaceArgument(0, $enabledFilters)
            ->replaceArgument(1, $filtersParameters)
        ;

        if (!isset($entityManager['connection'])) {
            $entityManager['connection'] = $this->defaultConnection;
        }

        $container
            ->setDefinition(sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']), new DefinitionDecorator('doctrine.orm.entity_manager.abstract'))
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
            if (version_compare(Version::VERSION, "2.5.0-DEV") < 0) {
                throw new InvalidArgumentException('Entity listeners configuration requires doctrine-orm 2.5.0 or newer');
            }

            $entities = $entityManager['entity_listeners']['entities'];
            $listenerId = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $entityManager['name']);
            $listenerDef = $container->setDefinition($listenerId, new Definition('%doctrine.orm.listeners.attach_entity_listeners.class%'));

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

            $listenerDef->addTag('doctrine.event_listener', array('event' => 'loadClassMetadata'));
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
     *             dir: [bundle-mappings1/, bundle-mappings2/]
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
        $slcFactoryDef = $container
            ->setDefinition($slcFactoryId, new Definition('%doctrine.orm.second_level_cache.default_cache_factory.class%'))
            ->setArguments(array(new Reference($regionsId), new Reference($driverId)));

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
}
