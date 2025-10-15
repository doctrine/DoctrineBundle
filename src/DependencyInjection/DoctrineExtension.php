<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Logging\CacheLoggerChain;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\Cache\Region\DefaultRegion;
use Doctrine\ORM\Cache\Region\FileLockRegion;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\Proxy\Autoloader;
use Doctrine\ORM\Tools\AttachEntityListenersListener;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Persistence\Mapping\Driver\StaticPHPDriver;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Listener;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

use function array_flip;
use function array_keys;
use function array_merge;
use function array_replace;
use function array_values;
use function class_exists;
use function dirname;
use function glob;
use function in_array;
use function interface_exists;
use function is_dir;
use function realpath;
use function reset;
use function sprintf;
use function str_replace;

use const GLOB_NOSORT;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @internal
 *
 * @phpstan-type DBALConfig = array{
 *      connections: array<string, array{logging: bool, profiling: bool, profiling_collect_backtrace: bool, idle_connection_ttl: int}>,
 *      driver_schemes: array<string, string>,
 *      default_connection: string,
 *      types: array<string, string>,
 *  }
 */
final class DoctrineExtension extends Extension
{
    /**
     * Used inside metadata driver method to simplify aggregation of data.
     *
     * @var array<string, string> List of alias => namespace
     */
    private array $aliasMap = [];

    /**
     * Used inside metadata driver method to simplify aggregation of data.
     *
     * @var array<string, array<string, string>> List of driver type => prefix => path
     */
    private array $drivers = [];

    /**
     * @param array<string, mixed> $objectManager A configured object manager
     *
     * @throws InvalidArgumentException
     */
    private function loadMappingInformation(array $objectManager, ContainerBuilder $container): void
    {
        if ($objectManager['auto_mapping']) {
            // automatically register bundle mappings
            foreach (array_keys($container->getParameter('kernel.bundles')) as $bundle) {
                if (isset($objectManager['mappings'][$bundle])) {
                    continue;
                }

                $objectManager['mappings'][$bundle] = [
                    'mapping' => true,
                    'is_bundle' => true,
                ];
            }
        }

        foreach ($objectManager['mappings'] as $mappingName => $mappingConfig) {
            if ($mappingConfig !== null && $mappingConfig['mapping'] === false) {
                continue;
            }

            $mappingConfig = array_replace([
                'dir' => false,
                'type' => false,
                'prefix' => false,
            ], (array) $mappingConfig);

            $mappingConfig['dir'] = $container->getParameterBag()->resolveValue($mappingConfig['dir']);
            // a bundle configuration is detected by realizing that the specified dir is not absolute and existing
            if (! isset($mappingConfig['is_bundle'])) {
                $mappingConfig['is_bundle'] = ! is_dir((string) $mappingConfig['dir']);
            }

            if ($mappingConfig['is_bundle']) {
                $bundle         = null;
                $bundleMetadata = null;
                foreach ($container->getParameter('kernel.bundles') as $name => $class) {
                    if ($mappingName === $name) {
                        $bundle         = new ReflectionClass($class);
                        $bundleMetadata = $container->getParameter('kernel.bundles_metadata')[$name];

                        break;
                    }
                }

                if ($bundle === null) {
                    throw new InvalidArgumentException(sprintf('Bundle "%s" does not exist or it is not enabled.', $mappingName));
                }

                $mappingConfig = $this->getMappingDriverBundleConfigDefaults($mappingConfig, $bundle, $container, $bundleMetadata['path']);
                if (! $mappingConfig) {
                    continue;
                }
            } elseif (! $mappingConfig['type']) {
                $mappingConfig['type'] = 'attribute';
            }

            $this->assertValidMappingConfiguration($mappingConfig, $objectManager['name']);
            $this->setMappingDriverConfig($mappingConfig, $mappingName);
            $this->setMappingDriverAlias($mappingConfig, $mappingName);
        }
    }

    /**
     * Register the alias for this mapping driver.
     *
     * Aliases can be used in the Query languages of all the Doctrine object managers to simplify writing tasks.
     *
     * @param array<string, mixed> $mappingConfig
     */
    private function setMappingDriverAlias(array $mappingConfig, string $mappingName): void
    {
        if (isset($mappingConfig['alias'])) {
            $this->aliasMap[$mappingConfig['alias']] = $mappingConfig['prefix'];
        } else {
            $this->aliasMap[$mappingName] = $mappingConfig['prefix'];
        }
    }

    /**
     * Register the mapping driver configuration for later use with the object managers metadata driver chain.
     *
     * @param array<string, mixed> $mappingConfig
     *
     * @throws InvalidArgumentException
     */
    private function setMappingDriverConfig(array $mappingConfig, string $mappingName): void
    {
        $mappingDirectory = $mappingConfig['dir'];
        if (! is_dir($mappingDirectory)) {
            throw new InvalidArgumentException(sprintf('Invalid Doctrine mapping path given. Cannot load Doctrine mapping/bundle named "%s".', $mappingName));
        }

        $this->drivers[$mappingConfig['type']][$mappingConfig['prefix']] = realpath($mappingDirectory) ?: $mappingDirectory;
    }

    /**
     * If this is a bundle controlled mapping all the missing information can be autodetected by this method.
     *
     * Returns false when autodetection failed, an array of the completed information otherwise.
     *
     * @param array<string, mixed> $bundleConfig
     */
    private function getMappingDriverBundleConfigDefaults(
        array $bundleConfig,
        ReflectionClass $bundle,
        ContainerBuilder $container,
        string|null $bundleDir = null,
    ): array|false {
        $bundleClassDir = dirname($bundle->getFileName());
        $bundleDir    ??= $bundleClassDir;

        if (! $bundleConfig['type']) {
            $bundleConfig['type'] = $this->detectMetadataDriver($bundleDir, $container);

            if (! $bundleConfig['type'] && $bundleDir !== $bundleClassDir) {
                $bundleConfig['type'] = $this->detectMetadataDriver($bundleClassDir, $container);
            }
        }

        if (! $bundleConfig['type']) {
            // skip this bundle, no mapping information was found.
            return false;
        }

        if (! $bundleConfig['dir']) {
            if (in_array($bundleConfig['type'], ['staticphp', 'attribute'])) {
                $bundleConfig['dir'] = $bundleClassDir . '/' . $this->getMappingObjectDefaultName();
            } else {
                $bundleConfig['dir'] = $bundleDir . '/' . $this->getMappingResourceConfigDirectory($bundleDir);
            }
        } else {
            $bundleConfig['dir'] = $bundleDir . '/' . $bundleConfig['dir'];
        }

        if (! $bundleConfig['prefix']) {
            $bundleConfig['prefix'] = $bundle->getNamespaceName() . '\\' . $this->getMappingObjectDefaultName();
        }

        return $bundleConfig;
    }

    /**
     * Register all the collected mapping information with the object manager by registering the appropriate mapping drivers.
     *
     * @param array<string, mixed> $objectManager
     */
    private function registerMappingDrivers(array $objectManager, ContainerBuilder $container): void
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        if ($container->hasDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'))) {
            $chainDriverDef = $container->getDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'));
        } else {
            $chainDriverDef = new Definition($this->getMetadataDriverClass('driver_chain'));
        }

        foreach ($this->drivers as $driverType => $driverPaths) {
            $mappingService   = $this->getObjectManagerElementName($objectManager['name'] . '_' . $driverType . '_metadata_driver');
            $mappingDriverDef = new Definition($this->getMetadataDriverClass($driverType), [
                array_values($driverPaths),
            ]);

            if ($mappingDriverDef->getClass() === SimplifiedXmlDriver::class) {
                $mappingDriverDef->setArguments([array_flip($driverPaths)]);
                $mappingDriverDef->addMethodCall('setGlobalBasename', ['mapping']);
            }

            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', [new Reference($mappingService), $prefix]);
            }
        }

        $container->setDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'), $chainDriverDef);
    }

    /**
     * Assertion if the specified mapping information is valid.
     *
     * @param array<string, mixed> $mappingConfig
     *
     * @throws InvalidArgumentException
     */
    private function assertValidMappingConfiguration(array $mappingConfig, string $objectManagerName): void
    {
        if (! $mappingConfig['type'] || ! $mappingConfig['dir'] || ! $mappingConfig['prefix']) {
            throw new InvalidArgumentException(sprintf('Mapping definitions for Doctrine manager "%s" require at least the "type", "dir" and "prefix" options.', $objectManagerName));
        }

        if (! is_dir($mappingConfig['dir'])) {
            throw new InvalidArgumentException(sprintf('Specified non-existing directory "%s" as Doctrine mapping source.', $mappingConfig['dir']));
        }

        if (! in_array($mappingConfig['type'], ['xml', 'php', 'staticphp', 'attribute'])) {
            throw new InvalidArgumentException(sprintf('Can only configure "xml", "php", "staticphp" or "attribute" through the DoctrineBundle. Use your own bundle to configure other metadata drivers. You can register them by adding a new driver to the "%s" service definition.', $this->getObjectManagerElementName($objectManagerName . '_metadata_driver')));
        }
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     */
    private function detectMetadataDriver(string $dir, ContainerBuilder $container): string|null
    {
        $configPath = $this->getMappingResourceConfigDirectory($dir);
        $extension  = $this->getMappingResourceExtension();

        if (glob($dir . '/' . $configPath . '/*.' . $extension . '.xml', GLOB_NOSORT)) {
            $driver = 'xml';
        } elseif (glob($dir . '/' . $configPath . '/*.' . $extension . '.yml', GLOB_NOSORT)) {
            $driver = 'yml';
        } elseif (glob($dir . '/' . $configPath . '/*.' . $extension . '.php', GLOB_NOSORT)) {
            $driver = 'php';
        } else {
            // add the closest existing directory as a resource
            $resource = $dir . '/' . $configPath;
            while (! is_dir($resource)) {
                $resource = dirname($resource);
            }

            $container->fileExists($resource, false);

            if ($container->fileExists($dir . '/' . $this->getMappingObjectDefaultName(), false)) {
                return 'attribute';
            }

            return null;
        }

        $container->fileExists($dir . '/' . $configPath, false);

        return $driver;
    }

    /**
     * Returns a modified version of $managerConfigs.
     *
     * The manager called $autoMappedManager will map all bundles that are not mapped by other managers.
     *
     * @param array<string, array<string, mixed>> $managerConfigs
     * @param array<string, string>               $bundles
     *
     * @return array<string, array<string, mixed>>
     */
    private function fixManagersAutoMappings(array $managerConfigs, array $bundles): array
    {
        $autoMappedManager = $this->validateAutoMapping($managerConfigs);

        if ($autoMappedManager !== null) {
            foreach (array_keys($bundles) as $bundle) {
                foreach ($managerConfigs as $manager) {
                    if (isset($manager['mappings'][$bundle])) {
                        continue 2;
                    }
                }

                $managerConfigs[$autoMappedManager]['mappings'][$bundle] = [
                    'mapping' => true,
                    'is_bundle' => true,
                ];
            }

            $managerConfigs[$autoMappedManager]['auto_mapping'] = false;
        }

        return $managerConfigs;
    }

    /**
     * Search for a manager that is declared as 'auto_mapping' = true.
     *
     * @param array<string, array<string, mixed>> $managerConfigs
     *
     * @throws LogicException
     */
    private function validateAutoMapping(array $managerConfigs): string|null
    {
        $autoMappedManager = null;
        foreach ($managerConfigs as $name => $manager) {
            if (! $manager['auto_mapping']) {
                continue;
            }

            if ($autoMappedManager !== null) {
                throw new LogicException(sprintf('You cannot enable "auto_mapping" on more than one manager at the same time (found in "%s" and "%s"").', $autoMappedManager, $name));
            }

            $autoMappedManager = $name;
        }

        return $autoMappedManager;
    }

    private string $defaultConnection;

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfigurationPrependingDefaults($configuration, $configs);

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
     * Process user configuration and adds a default DBAL connection and/or a
     * default EM if required, then process again the configuration to get
     * default values for each.
     *
     * @param array<array<mixed>> $configs
     *
     * @return array<mixed>
     */
    private function processConfigurationPrependingDefaults(ConfigurationInterface $configuration, array $configs): array
    {
        $config      = $this->processConfiguration($configuration, $configs);
        $configToAdd = [];

        // if no DB connection defined, prepend an empty one for the default
        // connection name in order to make Symfony Config resolve the default
        // values
        if (isset($config['dbal']) && empty($config['dbal']['connections'])) {
            $configToAdd['dbal'] = ['connections' => [($config['dbal']['default_connection'] ?? 'default') => []]];
        }

        // if no EM defined, prepend an empty one for the default EM name in
        // order to make Symfony Config resolve the default values
        if (isset($config['orm']) && empty($config['orm']['entity_managers'])) {
            $configToAdd['orm'] = ['entity_managers' => [($config['orm']['default_entity_manager'] ?? 'default') => []]];
        }

        if (! $configToAdd) {
            return $config;
        }

        return $this->processConfiguration($configuration, array_merge([$configToAdd], $configs));
    }

    /**
     * Loads the DBAL configuration.
     *
     * Usage example:
     *
     *      <doctrine:dbal id="myconn" dbname="sfweb" user="root" />
     *
     * @param DBALConfig       $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function dbalLoad(array $config, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('dbal.php');

        if (empty($config['default_connection'])) {
            $keys                         = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $this->defaultConnection = $config['default_connection'];

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $this->defaultConnection));
        $container->getAlias('database_connection')->setPublic(true);
        $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $this->defaultConnection), false));

        $container->setParameter('doctrine.dbal.connection_factory.types', $config['types']);

        $container->getDefinition('doctrine.dbal.connection_factory.dsn_parser')->setArgument(0, array_merge(ConnectionFactory::DEFAULT_SCHEME_MAP, $config['driver_schemes']));

        $connections = [];

        foreach (array_keys($config['connections']) as $name) {
            $connections[$name] = sprintf('doctrine.dbal.%s_connection', $name);
        }

        $container->setParameter('doctrine.connections', $connections);
        $container->setParameter('doctrine.default_connection', $this->defaultConnection);

        $connWithLogging   = [];
        $connWithProfiling = [];
        $connWithBacktrace = [];
        $ttlByConnection   = [];

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

            if ($connection['idle_connection_ttl'] > 0) {
                $ttlByConnection[$name] = $connection['idle_connection_ttl'];
            }

            $this->loadDbalConnection($name, $connection, $container);
        }

        $container->registerForAutoconfiguration(MiddlewareInterface::class)->addTag('doctrine.middleware');

        $container->registerAttributeForAutoconfiguration(AsMiddleware::class, static function (ChildDefinition $definition, AsMiddleware $attribute): void {
            $priority = isset($attribute->priority) ? ['priority' => $attribute->priority] : [];

            if ($attribute->connections === []) {
                $definition->addTag('doctrine.middleware', $priority);

                return;
            }

            foreach ($attribute->connections as $connName) {
                $definition->addTag('doctrine.middleware', array_merge($priority, ['connection' => $connName]));
            }
        });

        $this->registerDbalMiddlewares($container, $connWithLogging, $connWithProfiling, $connWithBacktrace, array_keys($ttlByConnection));

        $container->getDefinition('doctrine.dbal.idle_connection_middleware')->setArgument(1, $ttlByConnection);

        if (class_exists(Listener::class)) {
            return;
        }

        $container->removeDefinition('doctrine.dbal.idle_connection_listener');
        $container->removeDefinition('doctrine.dbal.idle_connection_middleware');
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param string               $name       The name of the connection
     * @param array<string, mixed> $connection A dbal connection configuration.
     * @param ContainerBuilder     $container  A ContainerBuilder instance
     */
    private function loadDbalConnection(string $name, array $connection, ContainerBuilder $container): void
    {
        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new ChildDefinition('doctrine.dbal.connection.configuration'));
        unset($connection['logging']);

        $dataCollectorDefinition = $container->getDefinition('data_collector.doctrine');
        $dataCollectorDefinition->replaceArgument(1, $connection['profiling_collect_schema_errors']);

        unset(
            $connection['profiling'],
            $connection['profiling_collect_backtrace'],
            $connection['profiling_collect_schema_errors'],
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
                $connection['mapping_types'],
            ]);

        $container
            ->registerAliasForArgument($connectionId, Connection::class, sprintf('%s.connection', $name))
            ->setPublic(false);

        // Set class in case "wrapper_class" option was used to assist IDEs
        if (isset($options['wrapperClass'])) {
            $def->setClass($options['wrapperClass']);
        }

        $container->setDefinition(
            ManagerRegistryAwareConnectionProvider::class,
            new Definition(ManagerRegistryAwareConnectionProvider::class, [$container->getDefinition('doctrine')]),
        );

        $configuration->addMethodCall('setSchemaManagerFactory', [new Reference($connection['schema_manager_factory'])]);

        if (! isset($connection['result_cache'])) {
            return;
        }

        $configuration->addMethodCall('setResultCache', [new Reference($connection['result_cache'])]);
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return mixed[]
     */
    private function getConnectionOptions(array $connection): array
    {
        $options = $connection;

        $connectionDefaults = [
            'host' => 'localhost',
            'port' => null,
            'user' => 'root',
            'password' => null,
        ];

        unset($options['schema_manager_factory']);

        $options += $connectionDefaults;

        foreach (array_keys($options['replicas']) as $name) {
            $options['replicas'][$name] += $connectionDefaults;
        }

        unset($options['mapping_types']);

        foreach (
            [
                'options' => 'driverOptions',
                'driver_class' => 'driverClass',
                'wrapper_class' => 'wrapperClass',
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

        foreach ($options['replica'] as $name => $value) {
            $driverOptions       = $value['driverOptions'] ?? [];
            $parentDriverOptions = $options['driverOptions'] ?? [];
            if ($driverOptions === [] && $parentDriverOptions === []) {
                continue;
            }

            $options['replica'][$name]['driverOptions'] = $driverOptions + $parentDriverOptions;
        }

        if (! empty($options['replica'])) {
            $nonRewrittenKeys = [
                'driver' => true,
                'driverClass' => true,
                'wrapperClass' => true,
                'keepReplica' => true,
                'platform' => true,
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
            unset($options['replica']);
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
    private function ormLoad(array $config, ContainerBuilder $container): void
    {
        if (! class_exists(UnitOfWork::class)) {
            throw new LogicException('To configure the ORM layer, you must first install the doctrine/orm package.');
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('orm.php');

        if (class_exists(AbstractType::class)) {
            $container->getDefinition('form.type.entity')->addTag('kernel.reset', ['method' => 'reset']);
        }

        if (! class_exists(UlidGenerator::class)) {
            $container->removeDefinition('doctrine.ulid_generator');
        }

        if (! class_exists(UuidGenerator::class)) {
            $container->removeDefinition('doctrine.uuid_generator');
        }

        if (! class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('doctrine.orm.entity_value_resolver.expression_language');
        }

        $controllerResolverDefaults = [];

        if (! $config['controller_resolver']['enabled']) {
            $controllerResolverDefaults['disabled'] = true;
        }

        if ($config['controller_resolver']['evict_cache']) {
            $controllerResolverDefaults['evict_cache'] = true;
        }

        $valueResolverDefinition = $container->getDefinition('doctrine.orm.entity_value_resolver');
        $valueResolverDefinition->setArgument(2, (new Definition(MapEntity::class))->setArguments([
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $controllerResolverDefaults['evict_cache'] ?? null,
            $controllerResolverDefaults['disabled'] ?? false,
        ]));

        // Symfony 7.3 and higher expose type alias support in the EntityValueResolver
        $valueResolverDefinition->setArgument(3, $config['resolve_target_entities']);

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

            $def
                ->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata])
                ->addTag('doctrine.event_listener', ['event' => Events::onClassMetadataNotFound]);
        }

        $container->registerForAutoconfiguration(ServiceEntityRepositoryInterface::class)
            ->addTag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

        $container->registerForAutoconfiguration(AbstractIdGenerator::class)
            ->addTag(IdGeneratorPass::ID_GENERATOR_TAG);

        $container->registerAttributeForAutoconfiguration(AsEntityListener::class, static function (ChildDefinition $definition, AsEntityListener $attribute): void {
            $definition->addTag('doctrine.orm.entity_listener', [
                'event'          => $attribute->event,
                'method'         => $attribute->method,
                'lazy'           => $attribute->lazy,
                'entity_manager' => $attribute->entityManager,
                'entity'         => $attribute->entity,
                'priority'       => $attribute->priority,
            ]);
        });
        $container->registerAttributeForAutoconfiguration(AsDoctrineListener::class, static function (ChildDefinition $definition, AsDoctrineListener $attribute): void {
            $definition->addTag('doctrine.event_listener', [
                'event'      => $attribute->event,
                'priority'   => $attribute->priority,
                'connection' => $attribute->connection,
            ]);
        });

        $container->registerAttributeForAutoconfiguration(Embeddable::class, static function (ChildDefinition $definition): void {
            $definition->setAbstract(true)->addTag('container.excluded', ['source' => sprintf('with #[%s] attribute', Embeddable::class)]);
        });
        $container->registerAttributeForAutoconfiguration(Entity::class, static function (ChildDefinition $definition): void {
            $definition->setAbstract(true)->addTag('container.excluded', ['source' => sprintf('with #[%s] attribute', Entity::class)]);
        });
        $container->registerAttributeForAutoconfiguration(MappedSuperclass::class, static function (ChildDefinition $definition): void {
            $definition->setAbstract(true)->addTag('container.excluded', ['source' => sprintf('with #[%s] attribute', MappedSuperclass::class)]);
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
    private function loadOrmEntityManager(array $entityManager, ContainerBuilder $container): void
    {
        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new ChildDefinition('doctrine.orm.configuration'));
        $ormConfigDef->addTag(IdGeneratorPass::CONFIGURATION_TAG);

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        if (isset($entityManager['entity_listener_resolver']) && $entityManager['entity_listener_resolver']) {
            $container->setAlias(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $entityManager['entity_listener_resolver']);
        } else {
            $definition = new Definition(ContainerEntityListenerResolver::class);
            $definition->addArgument(new Reference('service_container'));
            $container->setDefinition(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name']), $definition);
        }

        $methods = [
            'enableNativeLazyObjects' => true,
            'setMetadataCache' => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCache' => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCache' => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.orm.' . $entityManager['name'] . '_metadata_driver'),
            'setSchemaIgnoreClasses' => $entityManager['schema_ignore_classes'],
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
            'setNamingStrategy' => new Reference($entityManager['naming_strategy']),
            'setQuoteStrategy' => new Reference($entityManager['quote_strategy']),
            'setTypedFieldMapper' => new Reference($entityManager['typed_field_mapper']),
            'setEntityListenerResolver' => new Reference(sprintf('doctrine.orm.%s_entity_listener_resolver', $entityManager['name'])),
            'setIdentityGenerationPreferences' => $entityManager['identity_generation_preferences'],
        ];

        if (isset($entityManager['fetch_mode_subselect_batch_size'])) {
            $methods['setEagerFetchBatchSize'] = $entityManager['fetch_mode_subselect_batch_size'];
        }

        $listenerId        = sprintf('doctrine.orm.%s_listeners.attach_entity_listeners', $entityManager['name']);
        $listenerDef       = $container->setDefinition($listenerId, new Definition(AttachEntityListenersListener::class));
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
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection'])),
            ])
            ->setConfigurator([new Reference($managerConfiguratorName), 'configure']);

        $container
            ->registerAliasForArgument($entityManagerId, EntityManagerInterface::class, sprintf('%s.entity_manager', $entityManager['name']))
            ->setPublic(false);

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']), false),
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
     *         MyBundle2: xml
     *         MyBundle3: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle4: { type: attribute, dir: Entities/ }
     *         MyBundle5:
     *             type: xml
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
    private function loadOrmEntityManagerMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container): void
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers  = [];
        $this->aliasMap = [];

        $this->loadMappingInformation($entityManager, $container);
        $this->registerMappingDrivers($entityManager, $container);

        $container->getDefinition($this->getObjectManagerElementName($entityManager['name'] . '_metadata_driver'));
        /** @psalm-suppress NoValue $this->drivers is set by $this->loadMappingInformation() call  */
        foreach (array_keys($this->drivers) as $driverType) {
            $mappingService   = $this->getObjectManagerElementName($entityManager['name'] . '_' . $driverType . '_metadata_driver');
            $mappingDriverDef = $container->getDefinition($mappingService);
            $args             = $mappingDriverDef->getArguments();
            if ($driverType !== 'xml') {
                continue;
            }

            $args[1] ??= SimplifiedXmlDriver::DEFAULT_FILE_EXTENSION;
            $args[2]   = $entityManager['validate_xml_mapping'];

            $mappingDriverDef->setArguments($args);
        }

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
    private function loadOrmSecondLevelCache(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container): void
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
        $configDef  = $container->setDefinition($configId, new Definition(CacheConfiguration::class));
        $regionsDef = $container
            ->setDefinition($regionsId, new Definition(RegionsConfiguration::class))
            ->setArguments([$entityManager['second_level_cache']['region_lifetime'], $entityManager['second_level_cache']['region_lock_lifetime']]);

        $slcFactoryId = sprintf('doctrine.orm.%s_second_level_cache.default_cache_factory', $entityManager['name']);
        $factoryClass = $entityManager['second_level_cache']['factory'] ?? DefaultCacheFactory::class;

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
                        ->setDefinition($regionId, new Definition(DefaultRegion::class))
                        ->setArguments([$name, new Reference($driverId), $region['lifetime']]);
                }

                if ($regionType === 'filelock') {
                    $regionId = sprintf('doctrine.orm.%s_second_level_cache.region.%s_filelock', $entityManager['name'], $name);

                    $container
                        ->setDefinition($regionId, new Definition(FileLockRegion::class))
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
            $loggerChaingDef = $container->setDefinition($loggerChainId, new Definition(CacheLoggerChain::class));
            $loggerStatsDef  = $container->setDefinition($loggerStatsId, new Definition(StatisticsCacheLogger::class));

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
     * Prefixes the relative dependency injection container path with the object manager prefix.
     *
     * @example $name is 'entity_manager' then the result would be 'doctrine.orm.entity_manager'
     */
    private function getObjectManagerElementName(string $name): string
    {
        return 'doctrine.orm.' . $name;
    }

    /**
     * Noun that describes the mapped objects such as Entity or Document.
     *
     * Will be used for autodetection of persistent objects directory.
     */
    private function getMappingObjectDefaultName(): string
    {
        return 'Entity';
    }

    /**
     * Relative path from the bundle root to the directory where mapping files reside.
     */
    private function getMappingResourceConfigDirectory(string|null $bundleDir = null): string
    {
        if ($bundleDir !== null && is_dir($bundleDir . '/config/doctrine')) {
            return 'config/doctrine';
        }

        return 'Resources/config/doctrine';
    }

    /**
     * Extension used by the mapping files.
     */
    private function getMappingResourceExtension(): string
    {
        return 'orm';
    }

    /**
     * Loads a cache driver.
     *
     * @param array<string, mixed> $cacheDriver
     *
     * @throws InvalidArgumentException
     */
    private function loadCacheDriver(
        string $cacheName,
        string $objectManagerName,
        array $cacheDriver,
        ContainerBuilder $container,
    ): string {
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
                    $objectManagerName,
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
    private function loadOrmCacheDrivers(array $entityManager, ContainerBuilder $container): void
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
            $phpArrayFile         = '%kernel.build_dir%' . sprintf('/doctrine/orm/%s_metadata.php', $objectManagerName);
            $cacheWarmerServiceId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, 'metadata_cache_warmer'));

            $container->register($cacheWarmerServiceId, DoctrineMetadataCacheWarmer::class)
                ->setArguments([new Reference(sprintf('doctrine.orm.%s_entity_manager', $objectManagerName)), $phpArrayFile])
                ->addTag('kernel.cache_warmer', ['priority' => 1000]);

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

    public function getXsdValidationBasePath(): string
    {
        return __DIR__ . '/../../config/schema';
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

    /**
     * The class name used by the various mapping drivers.
     */
    private function getMetadataDriverClass(string $driverType): string
    {
        switch ($driverType) {
            case 'driver_chain':
                return MappingDriverChain::class;

            case 'xml':
                return SimplifiedXmlDriver::class;

            case 'php':
                return PHPDriver::class;

            case 'staticphp':
                return StaticPHPDriver::class;

            case 'attribute':
                return AttributeDriver::class;

            default:
                throw new LogicException(sprintf('Unknown "%s" metadata driver type.', $driverType));
        }
    }

    private function loadMessengerServices(ContainerBuilder $container): void
    {
        // If the Messenger component is installed, wire it:

        if (! interface_exists(MessageBusInterface::class)) {
            return;
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('messenger.php');

        /**
         * The Doctrine transport component (symfony/doctrine-messenger) is optional.
         * Remove service definition, if it is not available
         */
        if (class_exists(DoctrineTransportFactory::class)) {
            return;
        }

        $container->removeDefinition('messenger.transport.doctrine.factory');
        $container->removeDefinition('doctrine.orm.messenger.doctrine_schema_listener');
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
     * @param string[] $connWithTtl
     */
    private function registerDbalMiddlewares(
        ContainerBuilder $container,
        array $connWithLogging,
        array $connWithProfiling,
        array $connWithBacktrace,
        array $connWithTtl,
    ): void {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('middlewares.php');

        $loggingMiddlewareAbstractDef = $container->getDefinition('doctrine.dbal.logging_middleware');
        foreach ($connWithLogging as $connName) {
            $loggingMiddlewareAbstractDef->addTag('doctrine.middleware', ['connection' => $connName, 'priority' => 10]);
        }

        $container->getDefinition('doctrine.debug_data_holder')->replaceArgument(0, $connWithBacktrace);
        $debugMiddlewareAbstractDef = $container->getDefinition('doctrine.dbal.debug_middleware');
        foreach ($connWithProfiling as $connName) {
            $debugMiddlewareAbstractDef
                ->addTag('doctrine.middleware', ['connection' => $connName, 'priority' => 10]);
        }

        $idleConnectionMiddlewareAbstractDef = $container->getDefinition('doctrine.dbal.idle_connection_middleware');
        foreach ($connWithTtl as $connName) {
            $idleConnectionMiddlewareAbstractDef
                ->addTag('doctrine.middleware', ['connection' => $connName, 'priority' => 10]);
        }
    }
}
