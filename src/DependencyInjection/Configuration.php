<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;

use function array_diff_key;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function class_exists;
use function constant;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function key;
use function reset;
use function sprintf;
use function strtoupper;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    /** @param bool $debug Whether to use the debug mode */
    public function __construct(private bool $debug)
    {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine');
        $rootNode    = $treeBuilder->getRootNode();

        $this->addDbalSection($rootNode);
        $this->addOrmSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Add DBAL section to configuration tree
     */
    private function addDbalSection(ArrayNodeDefinition $node): void
    {
        // Key that should not be rewritten to the connection config
        $excludedKeys = ['default_connection' => true, 'driver_schemes' => true, 'driver_scheme' => true, 'types' => true, 'type' => true];

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->children()
            ->arrayNode('dbal')
                ->beforeNormalization()
                    ->ifTrue(static function ($v) use ($excludedKeys) {
                        if (! is_array($v)) {
                            return false;
                        }

                        if (array_key_exists('connections', $v) || array_key_exists('connection', $v)) {
                            return false;
                        }

                        // Is there actually anything to use once excluded keys are considered?
                        return (bool) array_diff_key($v, $excludedKeys);
                    })
                    ->then(static function ($v) use ($excludedKeys) {
                        $connection = [];
                        foreach ($v as $key => $value) {
                            if (isset($excludedKeys[$key])) {
                                continue;
                            }

                            $connection[$key] = $v[$key];
                            unset($v[$key]);
                        }

                        $v['connections'] = [($v['default_connection'] ?? 'default') => $connection];

                        return $v;
                    })
                ->end()
                ->children()
                    ->scalarNode('default_connection')->end()
                ->end()
                ->fixXmlConfig('type')
                ->children()
                    ->arrayNode('types')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn ($v) => ['class' => $v])
                            ->end()
                            ->children()
                                ->scalarNode('class')->isRequired()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('driver_scheme')
                ->children()
                    ->arrayNode('driver_schemes')
                        ->useAttributeAsKey('scheme')
                        ->normalizeKeys(false)
                        ->scalarPrototype()->end()
                        ->info('Defines a driver for given URL schemes. Schemes being driver names cannot be redefined. However, other default schemes can be overwritten.')
                        ->validate()
                            ->always()
                            ->then(static function (array $value) {
                                $unsupportedSchemes = [];

                                foreach ($value as $scheme => $driver) {
                                    if (! in_array($scheme, ['pdo-mysql', 'pdo-sqlite', 'pdo-pgsql', 'pdo-oci', 'oci8', 'ibm-db2', 'pdo-sqlsrv', 'mysqli', 'pgsql', 'sqlsrv', 'sqlite3'], true)) {
                                        continue;
                                    }

                                    $unsupportedSchemes[] = $scheme;
                                }

                                if ($unsupportedSchemes) {
                                    throw new InvalidArgumentException(sprintf('Registering a scheme with the name of one of the official drivers is forbidden, as those are defined in DBAL itself. The following schemes are forbidden: %s', implode(', ', $unsupportedSchemes)));
                                }

                                return $value;
                            })
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('connection')
                ->append($this->getDbalConnectionsNode())
            ->end();
    }

    /**
     * Return the dbal connections node
     */
    private function getDbalConnectionsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('connections');
        $node        = $treeBuilder->getRootNode();

        $connectionNode = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array');

        $this->configureDbalDriverNode($connectionNode);

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $connectionNode
            ->fixXmlConfig('option')
            ->fixXmlConfig('mapping_type')
            ->fixXmlConfig('replica')
            ->fixXmlConfig('default_table_option')
            ->children()
                ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
                ->booleanNode('auto_commit')->end()
                ->scalarNode('schema_filter')->end()
                ->booleanNode('logging')->defaultValue($this->debug)->end()
                ->booleanNode('profiling')->defaultValue($this->debug)->end()
                ->booleanNode('profiling_collect_backtrace')
                    ->defaultValue(false)
                    ->info('Enables collecting backtraces when profiling is enabled')
                ->end()
                ->booleanNode('profiling_collect_schema_errors')
                    ->defaultValue(true)
                    ->info('Enables collecting schema errors when profiling is enabled')
                ->end()
                ->scalarNode('server_version')->end()
                ->integerNode('idle_connection_ttl')->defaultValue(600)->end()
                ->scalarNode('driver_class')->end()
                ->scalarNode('wrapper_class')->end()
                ->booleanNode('keep_replica')->end()
                ->arrayNode('options')
                    ->useAttributeAsKey('key')
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('mapping_types')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('default_table_options')
                ->info(
                    "This option is used by the schema-tool and affects generated SQL. Possible keys include 'charset','collation', and 'engine'.",
                )
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('schema_manager_factory')
                    ->cannotBeEmpty()
                    ->defaultValue('doctrine.dbal.default_schema_manager_factory')
                ->end()
                ->scalarNode('result_cache')->end()
            ->end();

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $replicaNode = $connectionNode
            ->children()
                ->arrayNode('replicas')
                    ->useAttributeAsKey('name')
                    ->prototype('array');
        $this->configureDbalDriverNode($replicaNode);

        return $node;
    }

    /**
     * Adds config keys related to params processed by the DBAL drivers
     *
     * These keys are available for replica configurations too.
     */
    private function configureDbalDriverNode(ArrayNodeDefinition $node): void
    {
        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->validate()
            ->always(static function (array $values) {
                if (! isset($values['url'])) {
                    return $values;
                }

                $urlConflictingOptions = ['host' => true, 'port' => true, 'user' => true, 'password' => true, 'path' => true, 'dbname' => true, 'unix_socket' => true, 'memory' => true];
                $urlConflictingValues  = array_keys(array_intersect_key($values, $urlConflictingOptions));

                if ($urlConflictingValues) {
                    $tail = count($urlConflictingValues) > 1 ? sprintf('or "%s" options', array_pop($urlConflictingValues)) : 'option';

                    throw new RuntimeException(sprintf(
                        'Setting the "doctrine.dbal.%s" %s while the "url" one is defined is not allowed.',
                        implode('", "', $urlConflictingValues),
                        $tail,
                    ));
                }

                return $values;
            })
            ->end()
            ->children()
                ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
                ->scalarNode('dbname')->end()
                ->scalarNode('host')->info('Defaults to "localhost" at runtime.')->end()
                ->scalarNode('port')->info('Defaults to null at runtime.')->end()
                ->scalarNode('user')->info('Defaults to "root" at runtime.')->end()
                ->scalarNode('password')->info('Defaults to null at runtime.')->end()
                ->scalarNode('dbname_suffix')->info('Adds the given suffix to the configured database name, this option has no effects for the SQLite platform')->end()
                ->scalarNode('application_name')->end()
                ->scalarNode('charset')->end()
                ->scalarNode('path')->end()
                ->booleanNode('memory')->end()
                ->scalarNode('unix_socket')->info('The unix socket to use for MySQL')->end()
                ->booleanNode('persistent')->info('True to use as persistent connection for the ibm_db2 driver')->end()
                ->scalarNode('protocol')->info('The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)')->end()
                ->booleanNode('service')
                    ->info('True to use SERVICE_NAME as connection parameter instead of SID for Oracle')
                ->end()
                ->scalarNode('servicename')
                    ->info(
                        'Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter ' .
                        'for Oracle depending on the service parameter.',
                    )
                ->end()
                ->scalarNode('sessionMode')
                    ->info('The session mode to use for the oci8 driver')
                ->end()
                ->scalarNode('server')
                    ->info('The name of a running database server to connect to for SQL Anywhere.')
                ->end()
                ->scalarNode('default_dbname')
                    ->info(
                        'Override the default database (postgres) to connect to for PostgreSQL connexion.',
                    )
                ->end()
                ->scalarNode('sslmode')
                    ->info(
                        'Determines whether or with what priority a SSL TCP/IP connection will be negotiated with ' .
                        'the server for PostgreSQL.',
                    )
                ->end()
                ->scalarNode('sslrootcert')
                    ->info(
                        'The name of a file containing SSL certificate authority (CA) certificate(s). ' .
                        'If the file exists, the server\'s certificate will be verified to be signed by one of these authorities.',
                    )
                ->end()
                ->scalarNode('sslcert')
                    ->info(
                        'The path to the SSL client certificate file for PostgreSQL.',
                    )
                ->end()
                ->scalarNode('sslkey')
                    ->info(
                        'The path to the SSL client key file for PostgreSQL.',
                    )
                ->end()
                ->scalarNode('sslcrl')
                    ->info(
                        'The file name of the SSL certificate revocation list for PostgreSQL.',
                    )
                ->end()
                ->booleanNode('pooled')->info('True to use a pooled server with the oci8/pdo_oracle driver')->end()
                ->booleanNode('MultipleActiveResultSets')->info('Configuring MultipleActiveResultSets for the pdo_sqlsrv driver')->end()
                ->scalarNode('instancename')
                ->info(
                    'Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection.' .
                    ' It is generally used to connect to an Oracle RAC server to select the name' .
                    ' of a particular instance.',
                )
                ->end()
                ->scalarNode('connectstring')
                ->info(
                    'Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.' .
                    'When using this option, you will still need to provide the user and password parameters, but the other ' .
                    'parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods' .
                    ' from Doctrine\DBAL\Connection will no longer function as expected.',
                )
                ->end()
            ->end()
            ->beforeNormalization()
                ->ifTrue(static fn ($v) => ! isset($v['sessionMode']) && isset($v['session_mode']))
                ->then(static function ($v) {
                    $v['sessionMode'] = $v['session_mode'];
                    unset($v['session_mode']);

                    return $v;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(static fn ($v) => ! isset($v['MultipleActiveResultSets']) && isset($v['multiple_active_result_sets']))
                ->then(static function ($v) {
                    $v['MultipleActiveResultSets'] = $v['multiple_active_result_sets'];
                    unset($v['multiple_active_result_sets']);

                    return $v;
                })
            ->end();
    }

    /**
     * Add the ORM section to configuration tree
     */
    private function addOrmSection(ArrayNodeDefinition $node): void
    {
        // Key that should not be rewritten to the entity-manager config
        $excludedKeys = [
            'default_entity_manager' => true,
            'enable_native_lazy_objects' => true,
            'resolve_target_entities' => true,
            'resolve_target_entity' => true,
            'controller_resolver' => true,
        ];

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->children()
                ->arrayNode('orm')
                    ->beforeNormalization()
                        ->ifTrue(static function ($v) use ($excludedKeys) {
                            if (! empty($v) && ! class_exists(EntityManager::class)) {
                                throw new LogicException('The doctrine/orm package is required when the doctrine.orm config is set.');
                            }

                            if (! is_array($v)) {
                                return false;
                            }

                            if (array_key_exists('entity_managers', $v) || array_key_exists('entity_manager', $v)) {
                                return false;
                            }

                            // Is there actually anything to use once excluded keys are considered?
                            return (bool) array_diff_key($v, $excludedKeys);
                        })
                        ->then(static function ($v) use ($excludedKeys) {
                            $entityManager = [];
                            foreach ($v as $key => $value) {
                                if (isset($excludedKeys[$key])) {
                                    continue;
                                }

                                $entityManager[$key] = $v[$key];
                                unset($v[$key]);
                            }

                            $v['entity_managers'] = [($v['default_entity_manager'] ?? 'default') => $entityManager];

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('default_entity_manager')->end()
                        ->booleanNode('enable_native_lazy_objects')
                            ->defaultTrue()
                            ->validate()
                                ->ifTrue(static fn ($v) => $v === false)
                                ->thenInvalid('The setting "enable_native_lazy_objects" can no longer be disabled and should not be set')
                            ->end()
                            ->setDeprecated(
                                'doctrine/doctrine-bundle',
                                '3.1',
                                'The "%node%" option is deprecated and will be removed in DoctrineBundle 4.0, as native lazy objects are now always enabled.',
                            )
                        ->end()
                        ->arrayNode('controller_resolver')
                            ->canBeDisabled()
                            ->children()
                                ->booleanNode('auto_mapping')
                                    ->defaultFalse()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => $v !== false)
                                        ->thenInvalid('The setting "controller_resolver.auto_mapping" can no longer be enabled and must be set to false')
                                    ->end()
                                    ->setDeprecated(
                                        'doctrine/doctrine-bundle',
                                        '3.1',
                                        'The "%path%.%node%" option is deprecated and will be removed in DoctrineBundle 4.0, as it only accepts `false` since 3.0.',
                                    )
                                    ->info('Set to true to enable using route placeholders as lookup criteria when the primary key doesn\'t match the argument name')
                                ->end()
                                ->booleanNode('evict_cache')
                                    ->info('Set to true to fetch the entity from the database instead of using the cache, if any')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->fixXmlConfig('entity_manager')
                    ->append($this->getOrmEntityManagersNode())
                    ->fixXmlConfig('resolve_target_entity', 'resolve_target_entities')
                    ->append($this->getOrmTargetEntityResolverNode())
                ->end()
            ->end();
    }

    /**
     * Return ORM target entity resolver node
     *
     * @return ArrayNodeDefinition<TreeBuilder<'array'>>
     */
    private function getOrmTargetEntityResolverNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('resolve_target_entities');
        $node        = $treeBuilder->getRootNode();

        $node
            ->useAttributeAsKey('interface')
            ->prototype('scalar')
                ->cannotBeEmpty()
            ->end();

        return $node;
    }

    /**
     * Return ORM entity listener node
     *
     * @return ArrayNodeDefinition<TreeBuilder<'array'>>
     */
    private function getOrmEntityListenersNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('entity_listeners');
        $node        = $treeBuilder->getRootNode();

        $normalizer = static function ($mappings) {
            $entities = [];

            foreach ($mappings as $entityClass => $mapping) {
                $listeners = [];

                foreach ($mapping as $listenerClass => $listenerEvent) {
                    $events = [];

                    foreach ($listenerEvent as $eventType => $eventMapping) {
                        if ($eventMapping === null) {
                            $eventMapping = [null];
                        }

                        foreach ($eventMapping as $method) {
                            $events[] = [
                                'type' => $eventType,
                                'method' => $method,
                            ];
                        }
                    }

                    $listeners[] = [
                        'class' => $listenerClass,
                        'event' => $events,
                    ];
                }

                $entities[] = [
                    'class' => $entityClass,
                    'listener' => $listeners,
                ];
            }

            return ['entities' => $entities];
        };

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->beforeNormalization()
                // Yaml normalization
                ->ifTrue(static fn ($v) => is_array(reset($v)) && is_string(key(reset($v))))
                ->then($normalizer)
            ->end()
            ->fixXmlConfig('entity', 'entities')
            ->children()
                ->arrayNode('entities')
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->fixXmlConfig('listener')
                        ->children()
                            ->arrayNode('listeners')
                                ->useAttributeAsKey('class')
                                ->prototype('array')
                                    ->fixXmlConfig('event')
                                    ->children()
                                        ->arrayNode('events')
                                            ->prototype('array')
                                                ->children()
                                                    ->scalarNode('type')->end()
                                                    ->scalarNode('method')->defaultNull()->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Return ORM entity manager node
     */
    private function getOrmEntityManagersNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('entity_managers');
        $node        = $treeBuilder->getRootNode();

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->append($this->getOrmCacheDriverNode('query_cache_driver'))
                ->append($this->getOrmCacheDriverNode('metadata_cache_driver'))
                ->append($this->getOrmCacheDriverNode('result_cache_driver'))
                ->append($this->getOrmEntityListenersNode())
                ->fixXmlConfig('schema_ignore_class', 'schema_ignore_classes')
                ->children()
                    ->scalarNode('connection')->end()
                    ->scalarNode('class_metadata_factory_name')->defaultValue(ClassMetadataFactory::class)->end()
                    ->scalarNode('default_repository_class')->defaultValue(EntityRepository::class)->end()
                    ->scalarNode('auto_mapping')->defaultFalse()->end()
                    ->scalarNode('naming_strategy')->defaultValue('doctrine.orm.naming_strategy.default')->end()
                    ->scalarNode('quote_strategy')->defaultValue('doctrine.orm.quote_strategy.default')->end()
                    ->scalarNode('typed_field_mapper')->defaultValue('doctrine.orm.typed_field_mapper.default')->end()
                    ->scalarNode('entity_listener_resolver')->defaultNull()->end()
                    ->scalarNode('fetch_mode_subselect_batch_size')->end()
                    ->scalarNode('repository_factory')->defaultValue('doctrine.orm.container_repository_factory')->end()
                    ->arrayNode('schema_ignore_classes')
                        ->prototype('scalar')->end()
                    ->end()
                    ->booleanNode('validate_xml_mapping')->defaultFalse()->info('Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.14 and will be mandatory in ORM 3.0. See https://github.com/doctrine/orm/pull/6728.')->end()
                ->end()
                ->children()
                    ->arrayNode('second_level_cache')
                        ->children()
                            ->append($this->getOrmCacheDriverNode('region_cache_driver'))
                            ->scalarNode('region_lock_lifetime')->defaultValue(60)->end()
                            ->booleanNode('log_enabled')->defaultValue($this->debug)->end()
                            ->scalarNode('region_lifetime')->defaultValue(3600)->end()
                            ->booleanNode('enabled')->defaultValue(true)->end()
                            ->scalarNode('factory')->end()
                        ->end()
                        ->fixXmlConfig('region')
                        ->children()
                            ->arrayNode('regions')
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->children()
                                        ->append($this->getOrmCacheDriverNode('cache_driver'))
                                        ->scalarNode('lock_path')->defaultValue('%kernel.cache_dir%/doctrine/orm/slc/filelock')->end()
                                        ->scalarNode('lock_lifetime')->defaultValue(60)->end()
                                        ->scalarNode('type')->defaultValue('default')->end()
                                        ->scalarNode('lifetime')->defaultValue(0)->end()
                                        ->scalarNode('service')->end()
                                        ->scalarNode('name')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('logger')
                        ->children()
                            ->arrayNode('loggers')
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('service')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('hydrator')
                ->children()
                    ->arrayNode('hydrators')
                        ->useAttributeAsKey('name')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->fixXmlConfig('mapping')
                ->children()
                    ->arrayNode('mappings')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn ($v) => ['type' => $v])
                            ->end()
                            ->treatNullLike([])
                            ->treatFalseLike(['mapping' => false])
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('mapping')->defaultValue(true)->end()
                                ->scalarNode('type')->end()
                                ->scalarNode('dir')->end()
                                ->scalarNode('alias')->end()
                                ->scalarNode('prefix')->end()
                                ->booleanNode('is_bundle')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('dql')
                        ->fixXmlConfig('string_function')
                        ->fixXmlConfig('numeric_function')
                        ->fixXmlConfig('datetime_function')
                        ->children()
                            ->arrayNode('string_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('numeric_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('datetime_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('filter')
                ->children()
                    ->arrayNode('filters')
                        ->info('Register SQL Filters in the entity manager')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn ($v) => ['class' => $v])
                            ->end()
                            ->beforeNormalization()
                                // The content of the XML node is returned as the "value" key so we need to rename it
                                ->ifTrue(static fn ($v) => is_array($v) && isset($v['value']))
                                ->then(static function ($v) {
                                    $v['class'] = $v['value'];
                                    unset($v['value']);

                                    return $v;
                                })
                            ->end()
                            ->fixXmlConfig('parameter')
                            ->children()
                                ->scalarNode('class')->isRequired()->end()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->arrayNode('parameters')
                                    ->useAttributeAsKey('name')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('identity_generation_preference')
                ->children()
                    ->arrayNode('identity_generation_preferences')
                        ->info('Configures the preferences for identity generation when using the AUTO strategy. Valid values are "SEQUENCE" or "IDENTITY".')
                        ->useAttributeAsKey('platform')
                        ->prototype('scalar')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn (string $v) => constant(ClassMetadata::class . '::GENERATOR_TYPE_' . strtoupper($v)))
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Return an ORM cache driver node for a given entity manager
     */
    private function getOrmCacheDriverNode(string $name): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);
        $node        = $treeBuilder->getRootNode();

        /** @phpstan-ignore class.notFound (Phpstan Symfony extension does not know yet how to deal with these) */
        $node
            ->beforeNormalization()
                ->ifString()
                ->then(static fn ($v): array => ['type' => $v])
            ->end()
            ->children()
                ->scalarNode('type')->defaultNull()->end()
                ->scalarNode('id')->end()
                ->scalarNode('pool')->end()
            ->end();

        if ($name !== 'metadata_cache_driver') {
            $node->addDefaultsIfNotSet();
        }

        return $node;
    }
}
