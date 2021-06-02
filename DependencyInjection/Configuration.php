<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection;

use Doctrine\ORM\EntityManager;
use ReflectionClass;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;

use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function assert;
use function class_exists;
use function constant;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function key;
use function method_exists;
use function reset;
use function sprintf;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;
use function trigger_deprecation;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 */
class Configuration implements ConfigurationInterface
{
    /** @var bool */
    private $debug;

    /** @param bool $debug Whether to use the debug mode */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
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
        $node
            ->children()
            ->arrayNode('dbal')
                ->beforeNormalization()
                    ->ifTrue(static function ($v) {
                        return is_array($v) && ! array_key_exists('connections', $v) && ! array_key_exists('connection', $v);
                    })
                    ->then(static function ($v) {
                        // Key that should not be rewritten to the connection config
                        $excludedKeys = ['default_connection' => true, 'types' => true, 'type' => true];
                        $connection   = [];
                        foreach ($v as $key => $value) {
                            if (isset($excludedKeys[$key])) {
                                continue;
                            }

                            $connection[$key] = $v[$key];
                            unset($v[$key]);
                        }

                        $v['default_connection'] = isset($v['default_connection']) ? (string) $v['default_connection'] : 'default';
                        $v['connections']        = [$v['default_connection'] => $connection];

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
                                ->then(static function ($v) {
                                    return ['class' => $v];
                                })
                            ->end()
                            ->children()
                                ->scalarNode('class')->isRequired()->end()
                                ->booleanNode('commented')
                                    ->setDeprecated(
                                        ...$this->getDeprecationMsg('The doctrine-bundle type commenting features were removed; the corresponding config parameter was deprecated in 2.0 and will be dropped in 3.0.', '2.0')
                                    )
                                ->end()
                            ->end()
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
        assert($connectionNode instanceof ArrayNodeDefinition);

        $this->configureDbalDriverNode($connectionNode);

        $connectionNode
            ->fixXmlConfig('option')
            ->fixXmlConfig('mapping_type')
            ->fixXmlConfig('slave')
            ->fixXmlConfig('replica')
            ->fixXmlConfig('shard')
            ->fixXmlConfig('default_table_option')
            ->children()
                ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
                ->scalarNode('platform_service')->end()
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
                ->scalarNode('driver_class')->end()
                ->scalarNode('wrapper_class')->end()
                ->scalarNode('shard_manager_class')->end()
                ->scalarNode('shard_choser')->end()
                ->scalarNode('shard_choser_service')->end()
                ->booleanNode('keep_slave')
                    ->setDeprecated(
                        ...$this->getDeprecationMsg('The "keep_slave" configuration key is deprecated since doctrine-bundle 2.2. Use the "keep_replica" configuration key instead.', '2.2')
                    )
                ->end()
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
                    ->info("This option is used by the schema-tool and affects generated SQL. Possible keys include 'charset','collate', and 'engine'.")
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
            ->end();

        // dbal < 2.11
        $slaveNode = $connectionNode
            ->children()
                ->arrayNode('slaves')
                    ->setDeprecated(
                        ...$this->getDeprecationMsg('The "slaves" configuration key will be renamed to "replicas" in doctrine-bundle 3.0. "slaves" is deprecated since doctrine-bundle 2.2.', '2.2')
                    )
                    ->useAttributeAsKey('name')
                    ->prototype('array');
        $this->configureDbalDriverNode($slaveNode);

        // dbal >= 2.11
        $replicaNode = $connectionNode
            ->children()
                ->arrayNode('replicas')
                    ->useAttributeAsKey('name')
                    ->prototype('array');
        $this->configureDbalDriverNode($replicaNode);

        $shardNode = $connectionNode
            ->children()
                ->arrayNode('shards')
                    ->prototype('array');

        $shardNode
            ->children()
                ->integerNode('id')
                    ->min(1)
                    ->isRequired()
                ->end()
            ->end();
        $this->configureDbalDriverNode($shardNode);

        return $node;
    }

    /**
     * Adds config keys related to params processed by the DBAL drivers
     *
     * These keys are available for replica configurations too.
     */
    private function configureDbalDriverNode(ArrayNodeDefinition $node): void
    {
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
                    trigger_deprecation(
                        'doctrine/doctrine-bundle',
                        '2.4',
                        'Setting the "doctrine.dbal.%s" %s while the "url" one is defined is deprecated',
                        implode('", "', $urlConflictingValues),
                        $tail
                    );
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
                ->booleanNode('override_url')->setDeprecated(...$this->getDeprecationMsg('The "doctrine.dbal.override_url" configuration key is deprecated.', '2.4'))->end()
                ->scalarNode('dbname_suffix')->end()
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
                        'for Oracle depending on the service parameter.'
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
                        'Override the default database (postgres) to connect to for PostgreSQL connexion.'
                    )
                ->end()
                ->scalarNode('sslmode')
                    ->info(
                        'Determines whether or with what priority a SSL TCP/IP connection will be negotiated with ' .
                        'the server for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslrootcert')
                    ->info(
                        'The name of a file containing SSL certificate authority (CA) certificate(s). ' .
                        'If the file exists, the server\'s certificate will be verified to be signed by one of these authorities.'
                    )
                ->end()
                ->scalarNode('sslcert')
                    ->info(
                        'The path to the SSL client certificate file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslkey')
                    ->info(
                        'The path to the SSL client key file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslcrl')
                    ->info(
                        'The file name of the SSL certificate revocation list for PostgreSQL.'
                    )
                ->end()
                ->booleanNode('pooled')->info('True to use a pooled server with the oci8/pdo_oracle driver')->end()
                ->booleanNode('MultipleActiveResultSets')->info('Configuring MultipleActiveResultSets for the pdo_sqlsrv driver')->end()
                ->booleanNode('use_savepoints')->info('Use savepoints for nested transactions')->end()
                ->scalarNode('instancename')
                ->info(
                    'Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection.' .
                    ' It is generally used to connect to an Oracle RAC server to select the name' .
                    ' of a particular instance.'
                )
                ->end()
                ->scalarNode('connectstring')
                ->info(
                    'Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.' .
                    'When using this option, you will still need to provide the user and password parameters, but the other ' .
                    'parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods' .
                    ' from Doctrine\DBAL\Connection will no longer function as expected.'
                )
                ->end()
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return ! isset($v['sessionMode']) && isset($v['session_mode']);
                })
                ->then(static function ($v) {
                    $v['sessionMode'] = $v['session_mode'];
                    unset($v['session_mode']);

                    return $v;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return ! isset($v['MultipleActiveResultSets']) && isset($v['multiple_active_result_sets']);
                })
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
        $node
            ->children()
                ->arrayNode('orm')
                    ->beforeNormalization()
                        ->ifTrue(static function ($v) {
                            if (! empty($v) && ! class_exists(EntityManager::class)) {
                                throw new LogicException('The doctrine/orm package is required when the doctrine.orm config is set.');
                            }

                            return $v === null || (is_array($v) && ! array_key_exists('entity_managers', $v) && ! array_key_exists('entity_manager', $v));
                        })
                        ->then(static function ($v) {
                            $v = (array) $v;
                            // Key that should not be rewritten to the connection config
                            $excludedKeys  = [
                                'default_entity_manager' => true,
                                'auto_generate_proxy_classes' => true,
                                'proxy_dir' => true,
                                'proxy_namespace' => true,
                                'resolve_target_entities' => true,
                                'resolve_target_entity' => true,
                            ];
                            $entityManager = [];
                            foreach ($v as $key => $value) {
                                if (isset($excludedKeys[$key])) {
                                    continue;
                                }

                                $entityManager[$key] = $v[$key];
                                unset($v[$key]);
                            }

                            $v['default_entity_manager'] = isset($v['default_entity_manager']) ? (string) $v['default_entity_manager'] : 'default';
                            $v['entity_managers']        = [$v['default_entity_manager'] => $entityManager];

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('default_entity_manager')->end()
                        ->scalarNode('auto_generate_proxy_classes')->defaultValue(false)
                            ->info('Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL"')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    $generationModes = $this->getAutoGenerateModes();

                                    if (is_int($v) && in_array($v, $generationModes['values']/*array(0, 1, 2, 3)*/)) {
                                        return false;
                                    }

                                    if (is_bool($v)) {
                                        return false;
                                    }

                                    if (is_string($v)) {
                                        if (in_array(strtoupper($v), $generationModes['names']/*array('NEVER', 'ALWAYS', 'FILE_NOT_EXISTS', 'EVAL')*/)) {
                                            return false;
                                        }
                                    }

                                    return true;
                                })
                                ->thenInvalid('Invalid auto generate mode value %s')
                            ->end()
                            ->validate()
                                ->ifString()
                                ->then(static function ($v) {
                                    return constant('Doctrine\Common\Proxy\AbstractProxyFactory::AUTOGENERATE_' . strtoupper($v));
                                })
                            ->end()
                        ->end()
                        ->scalarNode('proxy_dir')->defaultValue('%kernel.cache_dir%/doctrine/orm/Proxies')->end()
                        ->scalarNode('proxy_namespace')->defaultValue('Proxies')->end()
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

        $node
            ->beforeNormalization()
                // Yaml normalization
                ->ifTrue(static function ($v) {
                    return is_array(reset($v)) && is_string(key(reset($v)));
                })
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

        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->append($this->getOrmCacheDriverNode('query_cache_driver'))
                ->append($this->getOrmCacheDriverNode('metadata_cache_driver'))
                ->append($this->getOrmCacheDriverNode('result_cache_driver'))
                ->append($this->getOrmEntityListenersNode())
                ->children()
                    ->scalarNode('connection')->end()
                    ->scalarNode('class_metadata_factory_name')->defaultValue('Doctrine\ORM\Mapping\ClassMetadataFactory')->end()
                    ->scalarNode('default_repository_class')->defaultValue('Doctrine\ORM\EntityRepository')->end()
                    ->scalarNode('auto_mapping')->defaultFalse()->end()
                    ->scalarNode('naming_strategy')->defaultValue('doctrine.orm.naming_strategy.default')->end()
                    ->scalarNode('quote_strategy')->defaultValue('doctrine.orm.quote_strategy.default')->end()
                    ->scalarNode('entity_listener_resolver')->defaultNull()->end()
                    ->scalarNode('repository_factory')->defaultValue('doctrine.orm.container_repository_factory')->end()
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
                                ->then(static function ($v) {
                                    return ['type' => $v];
                                })
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
                                ->then(static function ($v) {
                                    return ['class' => $v];
                                })
                            ->end()
                            ->beforeNormalization()
                                // The content of the XML node is returned as the "value" key so we need to rename it
                                ->ifTrue(static function ($v) {
                                    return is_array($v) && isset($v['value']);
                                })
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
            ->end();

        return $node;
    }

    /**
     * Return a ORM cache driver node for an given entity manager
     */
    private function getOrmCacheDriverNode(string $name): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);
        $node        = $treeBuilder->getRootNode();

        $node
            ->beforeNormalization()
                ->ifString()
                ->then(static function ($v): array {
                    return ['type' => $v];
                })
            ->end()
            ->children()
                ->scalarNode('type')->defaultNull()->end()
                ->scalarNode('id')->end()
                ->scalarNode('pool')->end()
            ->end();

        if ($name === 'metadata_cache_driver') {
            $node->setDeprecated(...$this->getDeprecationMsg(
                'The "metadata_cache_driver" configuration key is deprecated. Remove the configuration to have the cache created automatically.',
                '2.3'
            ));
        } else {
            $node->addDefaultsIfNotSet();
        }

        return $node;
    }

    /**
     * Find proxy auto generate modes for their names and int values
     *
     * @return array{names: list<string>, values: list<int>}
     */
    private function getAutoGenerateModes(): array
    {
        $constPrefix = 'AUTOGENERATE_';
        $prefixLen   = strlen($constPrefix);
        $refClass    = new ReflectionClass('Doctrine\Common\Proxy\AbstractProxyFactory');
        $constsArray = $refClass->getConstants();
        $namesArray  = [];
        $valuesArray = [];

        foreach ($constsArray as $key => $value) {
            if (strpos($key, $constPrefix) !== 0) {
                continue;
            }

            $namesArray[]  = substr($key, $prefixLen);
            $valuesArray[] = (int) $value;
        }

        return [
            'names' => $namesArray,
            'values' => $valuesArray,
        ];
    }

    /**
     * Returns the correct deprecation param's as an array for setDeprecated.
     *
     * Symfony/Config v5.1 introduces a deprecation notice when calling
     * setDeprecation() with less than 3 args and the getDeprecation method was
     * introduced at the same time. By checking if getDeprecation() exists,
     * we can determine the correct param count to use when calling setDeprecated.
     *
     * @return list<string>|array{0:string, 1: numeric-string, string}
     */
    private function getDeprecationMsg(string $message, string $version): array
    {
        if (method_exists(BaseNode::class, 'getDeprecation')) {
            return [
                'doctrine/doctrine-bundle',
                $version,
                $message,
            ];
        }

        return [$message];
    }
}
