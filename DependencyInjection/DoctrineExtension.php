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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\Config\FileLocator;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (!empty($config['dbal'])) {
            $this->dbalLoad($config['dbal'], $container);
        }

        if (!empty($config['orm'])) {
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

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', array($logger));
        }

        // event manager
        $def = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new DefinitionDecorator('doctrine.dbal.connection.event_manager'));

        // connection
        if (isset($connection['charset'])) {
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

        foreach (array(
            'options' => 'driverOptions',
            'driver_class' => 'driverClass',
            'wrapper_class' => 'wrapperClass',
        ) as $old => $new) {
            if (isset($options[$old])) {
                $options[$new] = $options[$old];
                unset($options[$old]);
            }
        }

        if (!empty($options['slaves'])) {
            $nonRewrittenKeys = array(
                'driver' => true, 'driverOptions' => true, 'driverClass' => true, 'wrapperClass' => true,
                'platform' => true, 'slaves' => true, 'master' => true,
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

        foreach ($config['entity_managers'] as $name => $entityManager) {
            $entityManager['name'] = $name;
            $this->loadOrmEntityManager($entityManager, $container);
        }

        if ($config['resolve_target_entities']) {
            $def = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
            foreach ($config['resolve_target_entities'] as $name => $implementation) {
                $def->addMethodCall('addResolveTargetEntity', array(
                    $name, $implementation, array()
                ));
            }

            $def->addTag('doctrine.event_listener', array('event' => 'loadClassMetadata'));
        }
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        if ($entityManager['auto_mapping'] && count($this->entityManagers) > 1) {
            throw new \LogicException('You cannot enable "auto_mapping" when several entity managers are defined.');
        }

        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new DefinitionDecorator('doctrine.orm.configuration'));

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        $methods = array(
            'setMetadataCacheImpl'        => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl'           => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl'          => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl'       => new Reference('doctrine.orm.'.$entityManager['name'].'_metadata_driver'),
            'setProxyDir'                 => '%doctrine.orm.proxy_dir%',
            'setProxyNamespace'           => '%doctrine.orm.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.orm.auto_generate_proxy_classes%',
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
        );
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
        foreach ($entityManager['filters'] as $name => $filter) {
            $ormConfigDef->addMethodCall('addFilter', array($name, $filter['class']));
            if ($filter['enabled']) {
                $enabledFilters[] = $name;
            }
        }

        $managerConfiguratorName = sprintf('doctrine.orm.%s_manager_configurator', $entityManager['name']);
        $managerConfiguratorDef = $container
            ->setDefinition($managerConfiguratorName, new DefinitionDecorator('doctrine.orm.manager_configurator.abstract'))
            ->replaceArgument(0, $enabledFilters)
        ;

        if (!isset($entityManager['connection'])) {
            $entityManager['connection'] = $this->defaultConnection;
        }

        $container
            ->setDefinition(sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']), new DefinitionDecorator('doctrine.orm.entity_manager.abstract'))
            ->setArguments(array(
                new Reference(sprintf('doctrine.dbal.%s_connection', $entityManager['connection'])),
                new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name']))
            ))
            ->setConfigurator(array(new Reference($managerConfiguratorName), 'configure'))
        ;

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']), false)
        );
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
     *             dir: %kernel.dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entities
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
     * Loads a configured entity managers cache drivers.
     *
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadOrmCacheDrivers(array $entityManager, ContainerBuilder $container)
    {
        $this->loadObjectManagerCacheDriver($entityManager, $container, 'metadata_cache');
        $this->loadObjectManagerCacheDriver($entityManager, $container, 'result_cache');
        $this->loadObjectManagerCacheDriver($entityManager, $container, 'query_cache');
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
