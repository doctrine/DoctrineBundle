<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Proxy\Autoloader;
use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;
use Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Reflection\RuntimeReflectionProperty;
use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\SchemaListener\PdoCacheAdapterDoctrineSchemaSubscriber;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\VarExporter\LazyGhostTrait;

use function array_intersect_key;
use function array_keys;
use function class_exists;
use function interface_exists;
use function is_dir;
use function method_exists;
use function reset;
use function sprintf;
use function str_replace;
use function trait_exists;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    private string $defaultConnection;

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
     * @param array<string, mixed> $config    An array of configuration settings
     * @param ContainerBuilder     $container A ContainerBuilder instance
     */
    protected function dbalLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('dbal.xml');

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

        $connWithLogging   = [];
        $connWithProfiling = [];
        $connWithBacktrace = [];
        foreach ($config['connections'] as $name => $connection) {
            if ($connection['logging']) {
                $connWithLogging[] = $name;
            }

            if ($connection['profiling']) {
                $connWithProfiling[] = $name;

                if ($connection['profiling_collect_backtrace']) {
                    $connWithBacktrace[] = $name;
                }
            }

            $this->loadDbalConnection($name, $connection, $container);
        }

        $container->registerForAutoconfiguration(MiddlewareInterface::class)->addTag('doctrine.middleware');

        $container->registerAttributeForAutoconfiguration(AsMiddleware::class, static function (ChildDefinition $definition, AsMiddleware $attribute) {
            if ($attribute->connections === []) {
                $definition->addTag('doctrine.middleware');

                return;
            }

            foreach ($attribute->connections as $connName) {
                $definition->addTag('doctrine.middleware', ['connection' => $connName]);
            }
        });

        $this->useMiddlewaresIfAvailable($container, $connWithLogging, $connWithProfiling, $connWithBacktrace);
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param string               $name       The name of the connection
     * @param array<string, mixed> $connection A dbal connection configuration.
     * @param ContainerBuilder     $container  A ContainerBuilder instance
     */
    protected function loadDbalConnection($name, array $connection, ContainerBuilder $container)
    {
        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new ChildDefinition('doctrine.dbal.connection.configuration'));
        unset($connection['logging']);

        $dataCollectorDefinition = $container->getDefinition('data_collector.doctrine');
        $dataCollectorDefinition->replaceArgument(1, $connection['profiling_collect_schema_errors']);

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

        // event manager
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new ChildDefinition('doctrine.dbal.connection.event_manager'));

        // connection
        $options = $this->getConnectionOptions($connection);

        $connectionId = sprintf('doctrine.dbal.%s_connection', $name);

        $def = $container
            ->setDefinition($connectionId, new ChildDefinition('doctrine.dbal.connection'))
            ->setPublic(true)
            ->setArguments([
                $options,
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name)),
                $connection['mapping_types'],
            ]);

        $container
            ->registerAliasForArgument($connectionId, Connection::class, sprintf('%sConnection', $name))
            ->setPublic(false);

        // Set class in case "wrapper_class" option was used to assist IDEs
        if (isset($options['wrapperClass'])) {
            $def->setClass($options['wrapperClass']);
        }

        if (! empty($connection['use_savepoints'])) {
            $def->addMethodCall('setNestTransactionsWithSavepoints', [$connection['use_savepoints']]);
        }

        $container->setDefinition(
            ManagerRegistryAwareConnectionProvider::class,
            new Definition(ManagerRegistryAwareConnectionProvider::class, [$container->getDefinition('doctrine')])
        );
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return mixed[]
     */
    protected function getConnectionOptions(array $connection): array
    {
        $options = $connection;

        $connectionDefaults = [
            'host' => 'localhost',
            'port' => null,
            'user' => 'root',
            'password' => null,
        ];

        if ($options['override_url'] ?? false) {
            $options['connection_override_options'] = array_intersect_key($options, ['dbname' => null] + $connectionDefaults);
        }

        unset($options['override_url']);

        $options += $connectionDefaults;

        foreach (['replicas', 'slaves'] as $connectionKey) {
            foreach (array_keys($options[$connectionKey]) as $name) {
                $options[$connectionKey][$name] += $connectionDefaults;
            }
        }

        if (isset($options['platform_service'])) {
            $options['platform'] = new Reference($options['platform_service']);
            unset($options['platform_service']);
        }

        unset($options['mapping_types']);

        foreach (
            [
                'options' => 'driverOptions',
                'driver_class' => 'driverClass',
                'wrapper_class' => 'wrapperClass',
                'keep_slave' => 'keepReplica',
                'keep_replica' => 'keepReplica',
                'replicas' => 'replica',
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

        foreach (['replica', 'slaves'] as $connectionKey) {
            foreach ($options[$connectionKey] as $name => $value) {
                $driverOptions       = $value['driverOptions'] ?? [];
                $parentDriverOptions = $options['driverOptions'] ?? [];
                if ($driverOptions === [] && $parentDriverOptions === []) {
                    continue;
                }

                $options[$connectionKey][$name]['driverOptions'] = $driverOptions + $parentDriverOptions;
            }
        }

        if (! empty($options['slaves']) || ! empty($options['replica'])) {
            $nonRewrittenKeys = [
                'driver' => true,
                'driverClass' => true,
                'wrapperClass' => true,
                'keepSlave' => true,
                'keepReplica' => true,
                'platform' => true,
                'slaves' => true,
                'primary' => true,
                'replica' => true,
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

                $options['primary'][$key] = $value;
                unset($options[$key]);
            }

            if (empty($options['wrapperClass'])) {
                // Change the wrapper class only if user did not configure custom one.
                $options['wrapperClass'] = PrimaryReadReplicaConnection::class;
            }
        } else {
            unset($options['slaves'], $options['replica']);
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
     * @param array<string, mixed> $config    An array of configuration settings
     * @param ContainerBuilder     $container A ContainerBuilder instance
     */
    protected function ormLoad(array $config, ContainerBuilder $container)
    {
        if (! class_exists(UnitOfWork::class)) {
            throw new LogicException('To configure the ORM layer, you must first install the doctrine/orm package.');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('orm.xml');

        if (! (new ReflectionMethod(EntityManager::class, '__construct'))->isPublic()) {
            $container->getDefinition('doctrine.orm.entity_manager.abstract')
                ->setFactory(['%doctrine.orm.entity_manager.class%', 'create']);
        }

        if (class_exists(AbstractType::class)) {
            $container->getDefinition('form.type.entity')->addTag('kernel.reset', ['method' => 'reset']);
        }

        if (! class_exists(Annotation::class)) {
            $container->removeAlias('doctrine.orm.metadata.annotation_reader');
        }

        // available in Symfony 5.1 and up to Symfony 5.4 (deprecated)
        if (! class_exists(PdoCacheAdapterDoctrineSchemaSubscriber::class)) {
            $container->removeDefinition('doctrine.orm.listeners.pdo_cache_adapter_doctrine_schema_subscriber');
        }

        if (! class_exists(UlidGenerator::class)) {
            $container->removeDefinition('doctrine.ulid_generator');
        }

        if (! class_exists(UuidGenerator::class)) {
            $container->removeDefinition('doctrine.uuid_generator');
        }

        // available in Symfony 6.2 and higher
        if (! class_exists(EntityValueResolver::class)) {
            $container->removeDefinition('doctrine.orm.entity_value_resolver');
            $container->removeDefinition('doctrine.orm.entity_value_resolver.expression_language');
        } else {
            if (! class_exists(ExpressionLanguage::class)) {
                $container->removeDefinition('doctrine.orm.entity_value_resolver.expression_language');
            }

            $controllerResolverDefaults = [];

            if (! $config['controller_resolver']['enabled']) {
                $controllerResolverDefaults['disabled'] = true;
            }

            if (! $config['controller_resolver']['auto_mapping']) {
                $controllerResolverDefaults['mapping'] = [];
            }

            if ($config['controller_resolver']['evict_cache']) {
                $controllerResolverDefaults['evict_cache'] = true;
            }

            if ($controllerResolverDefaults) {
                $container->getDefinition('doctrine.orm.entity_value_resolver')->setArgument(2, (new Definition(MapEntity::class))->setArguments([
                    null,
                    null,
                    null,
                    $controllerResolverDefaults['mapping'] ?? null,
                    null,
                    null,
                    null,
                    $controllerResolverDefaults['evict_cache'] ?? null,
                    $controllerResolverDefaults['disabled'] ?? false,
                ]));
            }
        }

        // not available in Doctrine ORM 3.0 and higher
        if (! class_exists(ConvertMappingCommand::class)) {
            $container->removeDefinition('doctrine.mapping_convert_command');
        }

        if (! class_exists(EnsureProductionSettingsCommand::class)) {
            $container->removeDefinition('doctrine.ensure_production_settings_command');
        }

        if (! class_exists(ClassMetadataExporter::class)) {
            $container->removeDefinition('doctrine.mapping_import_command');
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

        if ($config['enable_lazy_ghost_objects'] ?? false) {
            if (! method_exists(OrmConfiguration::class, 'setLazyGhostObjectEnabled')) {
                throw new LogicException(
                    'Lazy ghost objects cannot be enabled because the "doctrine/orm" library'
                    . ' version 2.14 or higher is not installed. Please run "composer update doctrine/orm".'
                );
            }

            // available in Symfony 6.2 and higher
            /** @psalm-suppress UndefinedClass */
            if (! trait_exists(LazyGhostTrait::class)) {
                throw new LogicException(
                    'Lazy ghost objects cannot be enabled because the "symfony/var-exporter" library'
                    . ' version 6.2 or higher is not installed. Please run "composer require symfony/var-exporter:^6.2".'
                );
            }

            if (! class_exists(RuntimeReflectionProperty::class)) {
                throw new LogicException(
                    'Lazy ghost objects cannot be enabled because the "doctrine/persistence" library'
                    . ' version 3.1 or higher is not installed. Please run "composer update doctrine/persistence".'
                );
            }
        }

        $options = ['auto_generate_proxy_classes', 'enable_lazy_ghost_objects', 'proxy_dir', 'proxy_namespace'];
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

            if (! interface_exists(LoaderInterface::class)) {
                continue;
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

        $container->registerForAutoconfiguration(AbstractIdGenerator::class)
            ->addTag(IdGeneratorPass::ID_GENERATOR_TAG);

        $container->registerAttributeForAutoconfiguration(AsEntityListener::class, static function (ChildDefinition $definition, AsEntityListener $attribute) {
            $definition->addTag('doctrine.orm.entity_listener', [
                'event'          => $attribute->event,
                'method'         => $attribute->method,
                'lazy'           => $attribute->lazy,
                'entity_manager' => $attribute->entityManager,
                'entity'         => $attribute->entity,
            ]);
        });
        $container->registerAttributeForAutoconfiguration(AsDoctrineListener::class, static function (ChildDefinition $definition, AsDoctrineListener $attribute) {
            $definition->addTag('doctrine.event_listener', [
                'event'      => $attribute->event,
                'priority'   => $attribute->priority,
                'connection' => $attribute->connection,
            ]);
        });

        /** @see DoctrineBundle::boot() */
        $container->getDefinition($defaultEntityManagerDefinitionId)
            ->addTag('container.preload', [
                'class' => Autoloader::class,
            ]);
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * @param array<string, mixed> $entityManager A configured ORM entity manager.
     * @param ContainerBuilder     $container     A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new ChildDefinition('doctrine.orm.configuration'));
        $ormConfigDef->addTag(IdGeneratorPass::CONFIGURATION_TAG);

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
            'setMetadataCache' => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCache' => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCache' => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.orm.' . $entityManager['name'] . '_metadata_driver'),
            'setProxyDir' => '%doctrine.orm.proxy_dir%',
            'setProxyNamespace' => '%doctrine.orm.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.orm.auto_generate_proxy_classes%',
            'setSchemaIgnoreClasses' => $entityManager['schema_ignore_classes'],
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
            'setNamingStrategy' => new Reference($entityManager['naming_strategy']),
            'setQuoteStrategy' => new Reference($entityManager['quote_strategy']),
            'setEntityListenerResolver' => new Reference(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name'])),
            'setLazyGhostObjectEnabled' => '%doctrine.orm.enable_lazy_ghost_objects%',
        ];

        if (! method_exists(OrmConfiguration::class, 'setLazyGhostObjectEnabled')) {
            unset($methods['setLazyGhostObjectEnabled']);
        }

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

        $entityManagerId = sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']);

        $container
            ->setDefinition($entityManagerId, new ChildDefinition('doctrine.orm.entity_manager.abstract'))
            ->setPublic(true)
            ->setArguments([
                new Reference(sprintf('doctrine.dbal.%s_connection', $entityManager['connection'])),
                new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name'])),
            ])
            ->setConfigurator([new Reference($managerConfiguratorName), 'configure']);

        $container
            ->registerAliasForArgument($entityManagerId, EntityManagerInterface::class, sprintf('%sEntityManager', $entityManager['name']))
            ->setPublic(false);

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']), false)
        );

        if (! isset($entityManager['entity_listeners'])) {
            return;
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
     * @param array<string, mixed> $entityManager A configured ORM entity manager
     * @param Definition           $ormConfigDef  A Definition instance
     * @param ContainerBuilder     $container     A ContainerBuilder instance
     *
     * @example
     *
     *  doctrine.orm:
     *     mappings:
     *         MyBundle1: ~
     *         MyBundle2: yml
     *         MyBundle3: { type: annotation, dir: Entities/ }
     *         MyBundle4: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle5: { type: attribute, dir: Entities/ }
     *         MyBundle6:
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
     * @param array<string, mixed> $entityManager A configured ORM entity manager
     * @param Definition           $ormConfigDef  A Definition instance
     * @param ContainerBuilder     $container     A ContainerBuilder instance
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
        $factoryClass = $entityManager['second_level_cache']['factory'] ?? '%doctrine.orm.second_level_cache.default_cache_factory.class%';

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

    protected function getMappingResourceConfigDirectory(?string $bundleDir = null): string
    {
        if ($bundleDir !== null && is_dir($bundleDir . '/config/doctrine')) {
            return 'config/doctrine';
        }

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
        $aliasId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, $cacheName));

        switch ($cacheDriver['type'] ?? 'pool') {
            case 'service':
                $serviceId = $cacheDriver['id'];
                break;

            case 'pool':
                $serviceId = $cacheDriver['pool'] ?? $this->createArrayAdapterCachePool($container, $objectManagerName, $cacheName);
                break;

            default:
                throw new InvalidArgumentException(sprintf(
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
     * @param array<string, mixed> $entityManager A configured ORM entity manager.
     */
    protected function loadOrmCacheDrivers(array $entityManager, ContainerBuilder $container)
    {
        if (isset($entityManager['metadata_cache_driver'])) {
            $this->loadCacheDriver('metadata_cache', $entityManager['name'], $entityManager['metadata_cache_driver'], $container);
        } else {
            $this->createMetadataCache($entityManager['name'], $container);
        }

        $this->loadCacheDriver('result_cache', $entityManager['name'], $entityManager['result_cache_driver'], $container);
        $this->loadCacheDriver('query_cache', $entityManager['name'], $entityManager['query_cache_driver'], $container);
    }

    private function createMetadataCache(string $objectManagerName, ContainerBuilder $container): void
    {
        $aliasId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, 'metadata_cache'));
        $cacheId = sprintf('cache.doctrine.orm.%s.%s', $objectManagerName, 'metadata');

        $cache = new Definition(ArrayAdapter::class);

        if (! $container->getParameter('kernel.debug')) {
            $phpArrayFile         = '%kernel.cache_dir%' . sprintf('/doctrine/orm/%s_metadata.php', $objectManagerName);
            $cacheWarmerServiceId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, 'metadata_cache_warmer'));

            $container->register($cacheWarmerServiceId, DoctrineMetadataCacheWarmer::class)
                ->setArguments([new Reference(sprintf('doctrine.orm.%s_entity_manager', $objectManagerName)), $phpArrayFile])
                ->addTag('kernel.cache_warmer', ['priority' => 1000]); // priority should be higher than ProxyCacheWarmer

            $cache = new Definition(PhpArrayAdapter::class, [$phpArrayFile, $cache]);
        }

        $container->setDefinition($cacheId, $cache);
        $container->setAlias($aliasId, $cacheId);
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
     * @param array<string, mixed> $objectManager
     * @param string               $cacheName
     *
     * @psalm-suppress MoreSpecificImplementedParamType
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
        return new Configuration((bool) $container->getParameter('kernel.debug'));
    }

    protected function getMetadataDriverClass(string $driverType): string
    {
        return '%' . $this->getObjectManagerElementName('metadata.' . $driverType . '.class') . '%';
    }

    private function loadMessengerServices(ContainerBuilder $container): void
    {
        // If the Messenger component is installed, wire it:

        /** @psalm-suppress UndefinedClass Optional dependency */
        if (! interface_exists(MessageBusInterface::class)) {
            return;
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('messenger.xml');

        /**
         * The Doctrine transport component (symfony/doctrine-messenger) is optional.
         * Remove service definition, if it is not available
         *
         * @psalm-suppress UndefinedClass Optional dependency
         */
        if (class_exists(DoctrineTransportFactory::class)) {
            return;
        }

        $container->removeDefinition('messenger.transport.doctrine.factory');
    }

    private function createArrayAdapterCachePool(ContainerBuilder $container, string $objectManagerName, string $cacheName): string
    {
        $id = sprintf('cache.doctrine.orm.%s.%s', $objectManagerName, str_replace('_cache', '', $cacheName));

        $poolDefinition = $container->register($id, ArrayAdapter::class);
        $poolDefinition->addTag('cache.pool');
        $container->setDefinition($id, $poolDefinition);

        return $id;
    }

    /**
     * @param string[] $connWithLogging
     * @param string[] $connWithProfiling
     * @param string[] $connWithBacktrace
     */
    private function useMiddlewaresIfAvailable(
        ContainerBuilder $container,
        array $connWithLogging,
        array $connWithProfiling,
        array $connWithBacktrace
    ): void {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('middlewares.xml');

        $loggingMiddlewareAbstractDef = $container->getDefinition('doctrine.dbal.logging_middleware');
        foreach ($connWithLogging as $connName) {
            $loggingMiddlewareAbstractDef->addTag('doctrine.middleware', ['connection' => $connName]);
        }

        $container->getDefinition('doctrine.debug_data_holder')->replaceArgument(0, $connWithBacktrace);
        $debugMiddlewareAbstractDef = $container->getDefinition('doctrine.dbal.debug_middleware');
        foreach ($connWithProfiling as $connName) {
            $debugMiddlewareAbstractDef
                ->addTag('doctrine.middleware', ['connection' => $connName]);
        }
    }
}
