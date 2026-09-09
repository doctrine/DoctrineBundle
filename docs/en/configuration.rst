Configuration Reference
=======================

.. configuration-block::

    .. code-block:: yaml

        doctrine:
            dbal:
                default_connection:           default

                # A collection of custom types
                types:
                    # example
                    some_custom_type:
                        class:                Acme\HelloBundle\MyCustomType

                connections:
                    # A collection of different named connections (e.g. default, conn2, etc)
                    default:
                        dbname:               ~
                        host:                 localhost
                        port:                 ~
                        user:                 root
                        password:             ~

                        # RDBMS specific; Refer to the manual of your RDBMS for more information
                        charset:              ~

                        # Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
                        dbname_suffix:        ~

                        # SQLite specific
                        path:                 ~

                        # SQLite specific
                        memory:               ~

                        # MySQL specific. The unix socket to use for MySQL
                        unix_socket:          ~

                        # IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                        persistent:           ~

                        # IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                        protocol:             ~

                        # Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                        service:              ~

                        # Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                        # parameter for Oracle depending on the service parameter.
                        servicename:          ~

                        # oci8 driver specific. The session mode to use for the oci8 driver.
                        sessionMode:          ~

                        # SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                        server:               ~

                        # PostgreSQL specific (default_dbname).
                        # Override the default database (postgres) to connect to.
                        default_dbname:       ~

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                        # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                        sslmode:              ~

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                        # The name of a file containing SSL certificate authority (CA) certificate(s).
                        # If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                        sslrootcert:          ~

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                        # The name of a file containing the client SSL certificate.
                        sslcert:              ~

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                        # The name of a file containing the private key for the client SSL certificate.
                        sslkey:               ~

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                        # The name of a file containing the SSL certificate revocation list (CRL).
                        sslcrl:               ~

                        # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                        pooled:               ~

                        # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                        MultipleActiveResultSets:  ~

                        driver:               pdo_mysql
                        platform_service:     ~
                        auto_commit:          ~

                        # If set to "/^sf2_/" all tables, and any named objects such as sequences
                        # not prefixed with "sf2_" will be ignored by the schema tool.
                        # This is for custom tables which should not be altered automatically.
                        schema_filter:        ~

                        # When true, queries are logged to a "doctrine" monolog channel
                        logging:              "%kernel.debug%"

                        profiling:            "%kernel.debug%"
                        # When true, profiling also collects a backtrace for each query
                        profiling_collect_backtrace: false
                        # When true, profiling also collects schema errors for each query
                        profiling_collect_schema_errors: true

                        server_version:       ~
                        driver_class:         ~
                        # Allows to specify a custom wrapper implementation to use.
                        # Must be a subclass of Doctrine\DBAL\Connection
                        wrapper_class:        ~
                        keep_replica:           ~

                        # An array of options
                        options:
                            # example
                            # key:                  value

                        # An array of mapping types
                        mapping_types:
                            # example
                            # enum:                 string

                        default_table_options:
                            # Affects schema-tool. If absent, DBAL chooses defaults
                            # based on the platform. Examples here are for MySQL.
                            # charset:      utf8mb4
                            # collation:    utf8mb4_unicode_ci
                            # engine:       InnoDB

                        # Service identifier of a Psr\Cache\CacheItemPoolInterface implementation
                        # to use as the cache driver for dbal result sets.
                        result_cache:        ~

                        replicas:
                            # A collection of named replica connections (e.g. replica1, replica2)
                            replica1:
                                dbname:               ~
                                host:                 localhost
                                port:                 ~
                                user:                 root
                                password:             ~
                                charset:              ~

                                # Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
                                dbname_suffix:        ~
                                path:                 ~
                                memory:               ~

                                # MySQL specific. The unix socket to use for MySQL
                                unix_socket:          ~

                                # IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                                persistent:           ~

                                # IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                                protocol:             ~

                                # Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                                service:              ~

                                # Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                                # parameter for Oracle depending on the service parameter.
                                servicename:          ~

                                # oci8 driver specific. The session mode to use for the oci8 driver.
                                sessionMode:          ~

                                # SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                                server:               ~

                                # PostgreSQL specific (default_dbname).
                                # Override the default database (postgres) to connect to.
                                default_dbname:       ~

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                                # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                                sslmode:              ~

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                                # The name of a file containing SSL certificate authority (CA) certificate(s).
                                # If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                                sslrootcert:          ~

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                                # The name of a file containing the client SSL certificate.
                                sslcert:              ~

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                                # The name of a file containing the private key for the client SSL certificate.
                                sslkey:               ~

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                                # The name of a file containing the SSL certificate revocation list (CRL).
                                sslcrl:               ~

                                # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                                pooled:               ~

                                # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                                MultipleActiveResultSets:  ~

            orm:
                default_entity_manager: ~ # The first defined is used if not set

                # No-op, deprecated, will be removed in the future
                enable_native_lazy_objects:   true

                identity_generation_preferences:
                    Doctrine\DBAL\Platforms\PostgreSQLPlatform: identity

                entity_managers:

                    # A collection of different named entity managers (e.g. some_em, another_em)
                    some_em:
                        query_cache_driver:
                            type: ~
                            id:   ~
                            pool: ~
                        metadata_cache_driver:
                            type: ~
                            id:   ~
                            pool: ~
                        result_cache_driver:
                            type: ~
                            id:   ~
                            pool: ~
                        entity_listeners:
                            entities:

                                # example
                                Acme\HelloBundle\Entity\Author:
                                    listeners:

                                        # example
                                        Acme\HelloBundle\EventListener\ExampleListener:
                                            events:
                                                type:                 preUpdate
                                                method:               preUpdate

                        # The name of a DBAL connection (the one marked as default is used if not set)
                        connection:           ~
                        class_metadata_factory_name:  Doctrine\ORM\Mapping\ClassMetadataFactory
                        default_repository_class:     Doctrine\ORM\EntityRepository
                        auto_mapping:                 false
                        # 0pt-in to the new mapping driver mode as of Doctrine ORM 2.14. See https://github.com/doctrine/orm/pull/6728.
                        validate_xml_mapping: false
                        naming_strategy:              doctrine.orm.naming_strategy.default
                        quote_strategy:               doctrine.orm.quote_strategy.default
                        typed_field_mapper:           doctrine.orm.typed_field_mapper.default
                        entity_listener_resolver:     ~
                        repository_factory:           ~
                        second_level_cache:
                            region_cache_driver:
                                type: ~
                                id:   ~
                                pool: ~
                            region_lock_lifetime: 60
                            log_enabled:          true
                            region_lifetime:      0
                            enabled:              true
                            factory:              ~
                            regions:

                                # Prototype
                                name:
                                    cache_driver:
                                        type: ~
                                        id:   ~
                                        pool: ~
                                    lock_path:            '%kernel.cache_dir%/doctrine/orm/slc/filelock'
                                    lock_lifetime:        60
                                    type:                 default
                                    lifetime:             0
                                    service:              ~
                                    name:                 ~
                            loggers:

                                # Prototype
                                name:
                                    name:                 ~
                                    service:              ~

                        # An array of hydrator names
                        hydrators:

                            # example
                            ListHydrator: Acme\HelloBundle\Hydrators\ListHydrator

                        mappings:
                            # An array of mappings, which may be a bundle name or something else
                            mapping_name:
                                mapping:              true
                                type:                 ~
                                dir:                  ~
                                alias:                ~
                                prefix:               ~
                                is_bundle:            ~

                        dql:
                            # A collection of string functions
                            string_functions:

                                # example
                                # test_string: Acme\HelloBundle\DQL\StringFunction

                            # A collection of numeric functions
                            numeric_functions:

                                # example
                                # test_numeric: Acme\HelloBundle\DQL\NumericFunction

                            # A collection of datetime functions
                            datetime_functions:

                                # example
                                # test_datetime: Acme\HelloBundle\DQL\DatetimeFunction

                        # Register SQL Filters in the entity manager
                        filters:

                            # An array of filters
                            some_filter:
                                class:                Acme\HelloBundle\Filter\SomeFilter # Required
                                enabled:              false

                                # An array of parameters
                                parameters:

                                    # example
                                    foo_param:              bar_value

                        schema_ignore_classes:
                            - Acme\AppBundle\Entity\Order
                            - Acme\AppBundle\Entity\PhoneNumber

                        fetch_mode_subselect_batch_size: 2000
                # Search for the "ResolveTargetEntityListener" class for a cookbook about this
                resolve_target_entities:

                    # Prototype
                    Acme\InvoiceBundle\Model\InvoiceSubjectInterface: Acme\AppBundle\Entity\Customer

    .. code-block:: php

        // config/packages/doctrine.php
        use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

        return static function (ContainerConfigurator $containerConfigurator): void {
            $containerConfigurator->extension('doctrine', [
                'dbal' => [
                    'default_connection' => 'default',

                    // A collection of custom types
                    'types' => [
                        // example
                        'some_custom_type' => [
                            'class' => 'Acme\HelloBundle\MyCustomType',
                        ],
                    ],

                    'connections' => [
                        // A collection of different named connections (e.g. default, conn2, etc)
                        'default' => [
                            'dbname' => null,
                            'host' => 'localhost',
                            'port' => null,
                            'user' => 'root',
                            'password' => null,

                            // RDBMS specific; Refer to the manual of your RDBMS for more information
                            'charset' => null,

                            // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
                            'dbname_suffix' => null,

                            // SQLite specific
                            'path' => null,

                            // SQLite specific
                            'memory' => null,

                            // MySQL specific. The unix socket to use for MySQL
                            'unix_socket' => null,

                            // IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                            'persistent' => null,

                            // IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                            'protocol' => null,

                            // Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                            'service' => null,

                            // Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                            // parameter for Oracle depending on the service parameter.
                            'servicename' => null,

                            // oci8 driver specific. The session mode to use for the oci8 driver.
                            'sessionMode' => null,

                            // SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                            'server' => null,

                            // PostgreSQL specific (default_dbname).
                            // Override the default database (postgres) to connect to.
                            'default_dbname' => null,

                            // PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                            // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                            'sslmode' => null,

                            // PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                            // The name of a file containing SSL certificate authority (CA) certificate(s).
                            // If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                            'sslrootcert' => null,

                            // PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                            // The name of a file containing the client SSL certificate.
                            'sslcert' => null,

                            // PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                            // The name of a file containing the private key for the client SSL certificate.
                            'sslkey' => null,

                            // PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                            // The name of a file containing the SSL certificate revocation list (CRL).
                            'sslcrl' => null,

                            // Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                            'pooled' => null,

                            // pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                            'MultipleActiveResultSets' => null,

                            'driver' => 'pdo_mysql',
                            'platform_service' => null,
                            'auto_commit' => null,

                            // If set to "/^sf2_/" all tables, and any named objects such as sequences
                            // not prefixed with "sf2_" will be ignored by the schema tool.
                            // This is for custom tables which should not be altered automatically.
                            'schema_filter' => null,

                            // When true, queries are logged to a "doctrine" monolog channel
                            'logging' => '%kernel.debug%',

                            'profiling' => '%kernel.debug%',
                            // When true, profiling also collects a backtrace for each query
                            'profiling_collect_backtrace' => false,
                            // When true, profiling also collects schema errors for each query
                            'profiling_collect_schema_errors' => true,

                            'server_version' => null,
                            'driver_class' => null,
                            // Allows to specify a custom wrapper implementation to use.
                            // Must be a subclass of Doctrine\DBAL\Connection
                            'wrapper_class' => null,
                            'keep_replica' => null,

                            // An array of options
                            'options' => [
                                // example
                                // 'key' => 'value',
                            ],

                            // An array of mapping types
                            'mapping_types' => [
                                // example
                                // 'enum' => 'string',
                            ],

                            'default_table_options' => [
                                // Affects schema-tool. If absent, DBAL chooses defaults
                                // based on the platform. Examples here are for MySQL.
                                // 'charset' => 'utf8mb4',
                                // 'collation' => 'utf8mb4_unicode_ci',
                                // 'engine' => 'InnoDB',
                            ],

                            // Service identifier of a Psr\Cache\CacheItemPoolInterface implementation
                            // to use as the cache driver for dbal result sets.
                            'result_cache' => null,

                            'replicas' => [
                                // A collection of named replica connections (e.g. replica1, replica2)
                                'replica1' => [
                                    'dbname' => null,
                                    'host' => 'localhost',
                                    'port' => null,
                                    'user' => 'root',
                                    'password' => null,
                                    'charset' => null,

                                    // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
                                    'dbname_suffix' => null,
                                    'path' => null,
                                    'memory' => null,

                                    // MySQL specific. The unix socket to use for MySQL
                                    'unix_socket' => null,

                                    // IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                                    'persistent' => null,

                                    // IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                                    'protocol' => null,

                                    // Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                                    'service' => null,

                                    // Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                                    // parameter for Oracle depending on the service parameter.
                                    'servicename' => null,

                                    // oci8 driver specific. The session mode to use for the oci8 driver.
                                    'sessionMode' => null,

                                    // SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                                    'server' => null,

                                    // PostgreSQL specific (default_dbname).
                                    // Override the default database (postgres) to connect to.
                                    'default_dbname' => null,

                                    // PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                                    // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                                    'sslmode' => null,

                                    // PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                                    // The name of a file containing SSL certificate authority (CA) certificate(s).
                                    // If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                                    'sslrootcert' => null,

                                    // PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                                    // The name of a file containing the client SSL certificate.
                                    'sslcert' => null,

                                    // PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                                    // The name of a file containing the private key for the client SSL certificate.
                                    'sslkey' => null,

                                    // PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                                    // The name of a file containing the SSL certificate revocation list (CRL).
                                    'sslcrl' => null,

                                    // Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                                    'pooled' => null,

                                    // pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                                    'MultipleActiveResultSets' => null,
                                ],
                            ],
                        ],
                    ],
                ],
                'orm' => [
                    'default_entity_manager' => null, // The first defined is used if not set

                    // No-op, deprecated, will be removed in the future
                    'enable_native_lazy_objects' => true,

                    'identity_generation_preferences' => [
                        'Doctrine\DBAL\Platforms\PostgreSQLPlatform' => 'identity',
                    ],

                    'entity_managers' => [
                        // A collection of different named entity managers (e.g. some_em, another_em)
                        'some_em' => [
                            'query_cache_driver' => [
                                'type' => null,
                                'id' => null,
                                'pool' => null,
                            ],
                            'metadata_cache_driver' => [
                                'type' => null,
                                'id' => null,
                                'pool' => null,
                            ],
                            'result_cache_driver' => [
                                'type' => null,
                                'id' => null,
                                'pool' => null,
                            ],
                            'entity_listeners' => [
                                'entities' => [
                                    // example
                                    'Acme\HelloBundle\Entity\Author' => [
                                        'listeners' => [
                                            // example
                                            'Acme\HelloBundle\EventListener\ExampleListener' => [
                                                'events' => [
                                                    'type' => 'preUpdate',
                                                    'method' => 'preUpdate',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],

                            // The name of a DBAL connection (the one marked as default is used if not set)
                            'connection' => null,
                            'class_metadata_factory_name' => 'Doctrine\ORM\Mapping\ClassMetadataFactory',
                            'default_repository_class' => 'Doctrine\ORM\EntityRepository',
                            'auto_mapping' => false,
                            // 0pt-in to the new mapping driver mode as of Doctrine ORM 2.14. See https://github.com/doctrine/orm/pull/6728.
                            'validate_xml_mapping' => false,
                            'naming_strategy' => 'doctrine.orm.naming_strategy.default',
                            'quote_strategy' => 'doctrine.orm.quote_strategy.default',
                            'typed_field_mapper' => 'doctrine.orm.typed_field_mapper.default',
                            'entity_listener_resolver' => null,
                            'repository_factory' => null,
                            'second_level_cache' => [
                                'region_cache_driver' => [
                                    'type' => null,
                                    'id' => null,
                                    'pool' => null,
                                ],
                                'region_lock_lifetime' => 60,
                                'log_enabled' => true,
                                'region_lifetime' => 0,
                                'enabled' => true,
                                'factory' => null,
                                'regions' => [
                                    // Prototype
                                    'name' => [
                                        'cache_driver' => [
                                            'type' => null,
                                            'id' => null,
                                            'pool' => null,
                                        ],
                                        'lock_path' => '%kernel.cache_dir%/doctrine/orm/slc/filelock',
                                        'lock_lifetime' => 60,
                                        'type' => 'default',
                                        'lifetime' => 0,
                                        'service' => null,
                                        'name' => null,
                                    ],
                                ],
                                'loggers' => [
                                    // Prototype
                                    'name' => [
                                        'name' => null,
                                        'service' => null,
                                    ],
                                ],
                            ],

                            // An array of hydrator names
                            'hydrators' => [
                                // example
                                'ListHydrator' => 'Acme\HelloBundle\Hydrators\ListHydrator',
                            ],

                            'mappings' => [
                                // An array of mappings, which may be a bundle name or something else
                                'mapping_name' => [
                                    'mapping' => true,
                                    'type' => null,
                                    'dir' => null,
                                    'alias' => null,
                                    'prefix' => null,
                                    'is_bundle' => null,
                                ],
                            ],

                            'dql' => [
                                // A collection of string functions
                                'string_functions' => [
                                    // example
                                    // 'test_string' => 'Acme\HelloBundle\DQL\StringFunction',
                                ],

                                // A collection of numeric functions
                                'numeric_functions' => [
                                    // example
                                    // 'test_numeric' => 'Acme\HelloBundle\DQL\NumericFunction',
                                ],

                                // A collection of datetime functions
                                'datetime_functions' => [
                                    // example
                                    // 'test_datetime' => 'Acme\HelloBundle\DQL\DatetimeFunction',
                                ],
                            ],

                            // Register SQL Filters in the entity manager
                            'filters' => [
                                // An array of filters
                                'some_filter' => [
                                    'class' => 'Acme\HelloBundle\Filter\SomeFilter', // Required
                                    'enabled' => false,

                                    // An array of parameters
                                    'parameters' => [
                                        // example
                                        'foo_param' => 'bar_value',
                                    ],
                                ],
                            ],

                            'schema_ignore_classes' => [
                                'Acme\AppBundle\Entity\Order',
                                'Acme\AppBundle\Entity\PhoneNumber',
                            ],

                            'fetch_mode_subselect_batch_size' => 2000,
                        ],
                    ],

                    // Search for the "ResolveTargetEntityListener" class for a cookbook about this
                    'resolve_target_entities' => [
                        // Prototype
                        'Acme\InvoiceBundle\Model\InvoiceSubjectInterface' => 'Acme\AppBundle\Entity\Customer',
                    ],
                ],
            ]);
        };

Configuration Overview
----------------------

This following configuration example shows all the configuration defaults that
the ORM resolves to:

.. code-block:: yaml

    doctrine:
        orm:
            auto_mapping: true
            # the standard distribution overrides this to be true in debug, false otherwise
            default_entity_manager: default
            metadata_cache_driver: ~
            query_cache_driver: ~
            result_cache_driver: ~

There are lots of other configuration options that you can use to overwrite
certain classes, but those are for very advanced use-cases only.

Oracle DB
~~~~~~~~~

If the environment format configured in oracle does not meet doctrine requirements,
you need to use a middleware so that doctrine is aware of the format
used by Oracle DB.

You can do so easily with

.. code-block:: yaml

    services:
        oracle.middleware:
            class: Doctrine\DBAL\Driver\OCI8\Middleware\InitializeSession
            tags:
                - { name: doctrine.middleware, connection: default }

The environment variables that doctrine is going to change in the Oracle DB session are:

.. code-block:: yaml

    NLS_TIME_FORMAT="HH24:MI:SS"
    NLS_DATE_FORMAT="YYYY-MM-DD HH24:MI:SS"
    NLS_TIMESTAMP_FORMAT="YYYY-MM-DD HH24:MI:SS"
    NLS_TIMESTAMP_TZ_FORMAT="YYYY-MM-DD HH24:MI:SS TZH:TZM"


Caching Drivers
~~~~~~~~~~~~~~~

You can use a Symfony Cache pool by using the ``pool`` type and creating a cache
pool through the FrameworkBundle configuration. The ``service`` type lets you
define the ``ID`` of your own caching service.

The following example shows an overview of the caching configurations:

.. code-block:: yaml

    doctrine:
        orm:
            auto_mapping: true
            # With no cache set, this defaults to a sane 'pool' configuration
            metadata_cache_driver: ~
            # the 'pool' type requires to define the 'pool' option and configure a cache pool using the FrameworkBundle
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
            # the 'service' type requires to define the 'id' option too
            query_cache_driver:
                type: service
                id: App\ORM\MyCacheService

    framework:
        cache:
            pools:
                doctrine.result_cache_pool:
                    adapter: cache.app

Mapping Configuration
~~~~~~~~~~~~~~~~~~~~~

Explicit definition of all the mapped entities is the only necessary
configuration for the ORM and there are several configuration options that you
can control. The following configuration options exist for a mapping:

``type``
    One of ``attribute``, ``xml``, ``php`` or ``staticphp``.
    This specifies which type of metadata type your mapping uses.

``dir``
    Path to the mapping or entity files (depending on the driver). If this path
    is relative it is assumed to be relative to the bundle root. This only works
    if the name of your mapping is a bundle name. If you want to use this option
    to specify absolute paths you should prefix the path with the kernel
    parameters that exist in the DIC (for example ``%kernel.root_dir%``).

``prefix``
    A common namespace prefix that all entities of this mapping share. This
    prefix should never conflict with prefixes of other defined mappings
    otherwise some of your entities cannot be found by Doctrine. This option
    defaults to the bundle namespace + ``Entity``, for example for an
    application bundle called ``AcmeHelloBundle`` prefix would be
    ``Acme\HelloBundle\Entity``.

``alias``
    Doctrine offers a way to alias entity namespaces to simpler, shorter names
    to be used in DQL queries or for Repository access. When using a bundle the
    alias defaults to the bundle name.

``is_bundle``
    This option is a derived value from ``dir`` and by default is set to true if
    dir is relative proved by a ``file_exists()`` check that returns false. It
    is false if the existence check returns true. In this case an absolute path
    was specified and the metadata files are most likely in a directory outside
    of a bundle.

Filters Configuration
~~~~~~~~~~~~~~~~~~~~~

You can easily define `doctrine filters`_ in your configuration file:

.. code-block:: yaml

    doctrine:
        orm:
            filters:
                myFilter:
                    class: MyVendor\MyBundle\Filters\MyFilter
                    enabled: true
                    parameters:
                        myParameter: myValue
                        mySecondParameter: mySecondValue

``myFilter``
    Filter identifier (Required)

``class``
    Filter target class (Required)

``enabled``
    Enable/Disable the filter by default (Optional - Default disabled)

``parameters:``
    Set default parameters (Optional)

``myParameter: myValue``
    Bind the value ``myValue`` to the parameter ``myParameter`` (Optional)

.. _doctrine filters: https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/filters.html

.. _`reference-dbal-configuration`:

Autowiring multiple Entity Managers
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can autowire different entity managers by type-hinting your service arguments with
the following syntax: ``Doctrine\ORM\EntityManagerInterface $<entity manager>EntityManager``.
For example, to inject a ``purchase_logs`` entity manager use this:

.. code-block:: diff

    -     public function __construct(EntityManagerInterface $entityManager)
    +     public function __construct(EntityManagerInterface $purchaseLogsEntityManager)
        {
            $this->entityManager = $purchaseLogsEntityManager;
        }

Doctrine DBAL Configuration
---------------------------

.. note::

    DoctrineBundle supports all parameters that default Doctrine drivers
    accept, converted to the XML or YAML naming standards that Symfony
    enforces. See the Doctrine `DBAL documentation`_ for more information.

Besides default Doctrine options, there are some Symfony-related ones that you
can configure. The following block shows all possible configuration keys:

.. configuration-block::

    .. code-block:: yaml

        doctrine:
            dbal:
                url:                      mysql://user:secret@localhost:1234/otherdatabase
                dbname:                   database
                host:                     localhost
                port:                     1234
                user:                     user
                password:                 secret
                dbname_suffix:            _test
                driver:                   pdo_mysql
                driver_class:             MyNamespace\MyDriverImpl
                options:
                    foo: bar
                path:                     "%kernel.project_dir%/var/data.db" # SQLite specific
                memory:                   true                               # SQLite specific
                unix_socket:              /tmp/mysql.sock
                persistent:               true
                MultipleActiveResultSets: true                # pdo_sqlsrv driver specific
                pooled:                   true                # Oracle specific (SERVER=POOLED)
                protocol:                 TCPIP               # IBM DB2 specific (PROTOCOL)
                server:                   my_database_server  # SQL Anywhere specific (ServerName)
                service:                  true                # Oracle specific (SERVICE_NAME instead of SID)
                servicename:              MyOracleServiceName # Oracle specific (SERVICE_NAME)
                sessionMode:              2                   # oci8 driver specific (session_mode)
                default_dbname:           database            # PostgreSQL specific (default_dbname)
                sslmode:                  require             # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE)
                sslrootcert:              postgresql-ca.pem   # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT)
                sslcert:                  postgresql-cert.pem # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT)
                sslkey:                   postgresql-key.pem  # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY)
                sslcrl:                   postgresql.crl      # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL)
                wrapper_class:            MyDoctrineDbalConnectionWrapper
                charset:                  ~                   # RDBMS-specific. Refer to the manual of your RDBMS for more information.
                logging:                  "%kernel.debug%"
                platform_service:         MyOwnDatabasePlatformService
                auto_commit:              false
                schema_filter:            /^sf2_/
                mapping_types:
                    enum: string
                types:
                    custom: Acme\HelloBundle\MyCustomType
                default_table_options:
                    # Affects schema-tool. If absent, DBAL chooses defaults
                    # based on the platform. These defaults might be
                    # sub-optimal for backward compatibility reasons.
                    charset:              utf8mb4
                    collation:            utf8mb4_unicode_ci
                    engine:               InnoDB

    .. code-block:: php

        // config/packages/doctrine.php
        use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

        return static function (ContainerConfigurator $containerConfigurator): void {
            $containerConfigurator->extension('doctrine', [
                'dbal' => [
                    'url' => 'mysql://user:secret@localhost:1234/otherdatabase',
                    'dbname' => 'database',
                    'host' => 'localhost',
                    'port' => 1234,
                    'user' => 'user',
                    'password' => 'secret',
                    'dbname_suffix' => '_test',
                    'driver' => 'pdo_mysql',
                    'driver_class' => 'MyNamespace\MyDriverImpl',
                    'options' => [
                        'foo' => 'bar',
                    ],
                    'path' => '%kernel.project_dir%/var/data.db', // SQLite specific
                    'memory' => true, // SQLite specific
                    'unix_socket' => '/tmp/mysql.sock',
                    'persistent' => true,
                    'MultipleActiveResultSets' => true, // pdo_sqlsrv driver specific
                    'pooled' => true, // Oracle specific (SERVER=POOLED)
                    'protocol' => 'TCPIP', // IBM DB2 specific (PROTOCOL)
                    'server' => 'my_database_server', // SQL Anywhere specific (ServerName)
                    'service' => true, // Oracle specific (SERVICE_NAME instead of SID)
                    'servicename' => 'MyOracleServiceName', // Oracle specific (SERVICE_NAME)
                    'sessionMode' => 2, // oci8 driver specific (session_mode)
                    'default_dbname' => 'database', // PostgreSQL specific (default_dbname)
                    'sslmode' => 'require', // PostgreSQL specific (LIBPQ-CONNECT-SSLMODE)
                    'sslrootcert' => 'postgresql-ca.pem', // PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT)
                    'sslcert' => 'postgresql-cert.pem', // PostgreSQL specific (LIBPQ-CONNECT-SSLCERT)
                    'sslkey' => 'postgresql-key.pem', // PostgreSQL specific (LIBPQ-CONNECT-SSLKEY)
                    'sslcrl' => 'postgresql.crl', // PostgreSQL specific (LIBPQ-CONNECT-SSLCRL)
                    'wrapper_class' => 'MyDoctrineDbalConnectionWrapper',
                    'charset' => null, // RDBMS-specific. Refer to the manual of your RDBMS for more information.
                    'logging' => '%kernel.debug%',
                    'platform_service' => 'MyOwnDatabasePlatformService',
                    'auto_commit' => false,
                    'schema_filter' => '/^sf2_/',
                    'mapping_types' => [
                        'enum' => 'string',
                    ],
                    'types' => [
                        'custom' => 'Acme\HelloBundle\MyCustomType',
                    ],
                    'default_table_options' => [
                        // Affects schema-tool. If absent, DBAL chooses defaults
                        // based on the platform. These defaults might be
                        // sub-optimal for backward compatibility reasons.
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'engine' => 'InnoDB',
                    ],
                ],
            ]);
        };

If you want to configure multiple connections in YAML, put them under the
``connections`` key and give them a unique name:

.. code-block:: yaml

    doctrine:
        dbal:
            default_connection:       default
            connections:
                default:
                    dbname:           Symfony2
                    user:             root
                    password:         null
                    host:             localhost
                customer:
                    dbname:           customer
                    user:             root
                    password:         null
                    host:             localhost

The ``database_connection`` service always refers to the *default* connection,
which is the first one defined or the one configured via the
``default_connection`` parameter.

Each connection is also accessible via the ``doctrine.dbal.[name]_connection``
service where ``[name]`` is the name of the connection.

Autowiring multiple Connections
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can autowire different connections by type-hinting your service arguments with
the following syntax: ``Doctrine\DBAL\Connection $<connection name>Connection``.
For example, to inject a connection with the name ``purchase_logs`` use this:

.. code-block:: diff

    -     public function __construct(Connection $connection)
    +     public function __construct(Connection $purchaseLogsConnection)
        {
            $this->connection = $purchaseLogsConnection;
        }

.. _DBAL documentation: https://www.doctrine-project.org/projects/doctrine-dbal/en/current/index.html
