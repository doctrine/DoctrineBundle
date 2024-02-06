.. index::
   single: Doctrine; ORM Configuration Reference
   single: Configuration Reference; Doctrine ORM

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

                        # Enable savepoints for nested transactions
                        use_savepoints: true

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

                        # When true, type comments are skipped in the database schema, matching the behavior of DBAL 4.
                        # This requires using the non-deprecated schema comparison APIs of DBAL.
                        disable_type_comments: false

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
                            # collate:      utf8mb4_unicode_ci # When using doctrine/dbal 2.x
                            # collation:    utf8mb4_unicode_ci # When using doctrine/dbal 3.x
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

                # Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL", "FILE_NOT_EXISTS_OR_CHANGED"
                auto_generate_proxy_classes:  false
                proxy_dir:                    "%kernel.cache_dir%/doctrine/orm/Proxies"
                proxy_namespace:              Proxies
                # Enables the new implementation of proxies based on lazy ghosts instead of using the legacy implementation
                enable_lazy_ghost_objects:    false

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
                        # Opt-in to new mapping driver mode as of Doctrine ORM 2.16, https://github.com/doctrine/orm/pull/10455
                        report_fields_where_declared: false
                        # 0pt-in to the new mapping driver mode as of Doctrine ORM 2.14. See https://github.com/doctrine/orm/pull/6728.
                        validate_xml_mapping: false
                        naming_strategy:              doctrine.orm.naming_strategy.default
                        quote_strategy:               doctrine.orm.quote_strategy.default
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

                # Search for the "ResolveTargetEntityListener" class for a cookbook about this
                resolve_target_entities:

                    # Prototype
                    Acme\InvoiceBundle\Model\InvoiceSubjectInterface: Acme\AppBundle\Entity\Customer

    .. code-block:: xml

        <?xml version="1.0" encoding="UTF-8" ?>
        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:doctrine="http://symfony.com/schema/dic/doctrine"
            xsi:schemaLocation="http://symfony.com/schema/dic/services
                http://symfony.com/schema/dic/services/services-1.0.xsd
                http://symfony.com/schema/dic/doctrine
                http://symfony.com/schema/dic/doctrine/doctrine-1.0.xsd">

            <doctrine:config>

                <doctrine:dbal default-connection="default">

                    <!-- example -->
                    <!-- class: Required -->
                    <doctrine:type
                        name="some_custom_type"
                        class="Acme\HelloBundle\MyCustomType"
                    />

                    <!-- example -->
                    <!-- unix-socket: The unix socket to use for MySQL -->
                    <!-- persistent: True to use as persistent connection for the ibm_db2 driver -->
                    <!-- protocol: The protocol to use for the ibm_db2 driver (default to TCPIP if omitted) -->
                    <!-- service: True to use SERVICE_NAME as connection parameter instead of SID for Oracle -->
                    <!-- servicename: Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter. -->
                    <!-- sessionMode: The session mode to use for the oci8 driver -->
                    <!-- server: The name of a running database server to connect to for SQL Anywhere. -->
                    <!-- default_dbname: Override the default database (postgres) to connect to for PostgreSQL. -->
                    <!-- sslmode: Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL. -->
                    <!-- sslrootcert: The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities. -->
                    <!-- sslcert: The name of a file containing a client SSL certificate -->
                    <!-- sslkey: The name of a file containing the private key used for the client SSL certificate -->
                    <!-- sslcrl: The name of a file containing the SSL certificate revocation list (CRL) -->
                    <!-- pooled: True to use a pooled server with the oci8/pdo_oracle driver -->
                    <!-- MultipleActiveResultSets: Configuring MultipleActiveResultSets for the pdo_sqlsrv driver -->
                    <!-- use-savepoints: Enable savepoints for nested transactions -->
                    <doctrine:connection
                        name="default"
                        dbname=""
                        host="localhost"
                        port="null"
                        user="root"
                        password="null"
                        charset="null"
                        path=""
                        memory=""
                        unix-socket=""
                        persistent=""
                        protocol=""
                        service=""
                        servicename=""
                        sessionMode=""
                        server=""
                        default_dbname=""
                        sslmode=""
                        sslrootcert=""
                        sslcert=""
                        sslkey=""
                        sslcrl=""
                        pooled=""
                        MultipleActiveResultSets=""
                        use-savepoints="true"
                        driver="pdo_mysql"
                        platform-service=""
                        auto-commit=""
                        schema-filter=""
                        logging="%kernel.debug%"
                        profiling="%kernel.debug%"
                        profiling-collect-backtrace="false"
                        profiling-collect-schema-errors="true"
                        disable-type-comments="false"
                        server-version=""
                        driver-class=""
                        wrapper-class=""
                        keep-replica=""
                    >

                        <!-- example -->
                        <doctrine:option key="key">value</doctrine:option>

                        <!-- example -->
                        <doctrine:mapping-type name="enum">string</doctrine:mapping-type>

                        <!-- example -->
                        <doctrine:default-table-option name="charset">utf8mb4</doctrine:default-table-option>
                        <!-- when using doctrine/dbal 2.x -->
                        <doctrine:default-table-option name="collate">utf8mb4_unicode_ci</doctrine:default-table-option>
                        <!-- when using doctrine/dbal 3.x -->
                        <doctrine:default-table-option name="collation">utf8_unicode_ci</doctrine:default-table-option>
                        <doctrine:default-table-option name="engine">InnoDB</doctrine:default-table-option>

                        <!-- example -->
                        <!-- unix-socket: The unix socket to use for MySQL -->
                        <!-- persistent: True to use as persistent connection for the ibm_db2 driver -->
                        <!-- protocol: The protocol to use for the ibm_db2 driver (default to TCPIP if omitted) -->
                        <!-- service: True to use SERVICE_NAME as connection parameter instead of SID for Oracle -->
                        <!-- servicename: Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter. -->
                        <!-- sessionMode: The session mode to use for the oci8 driver -->
                        <!-- server: The name of a running database server to connect to for SQL Anywhere. -->
                        <!-- default_dbname: Override the default database (postgres) to connect to for PostgreSQL. -->
                        <!-- sslmode: Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL. -->
                        <!-- sslrootcert: The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities. -->
                        <!-- sslcert: The name of a file containing a client SSL certificate -->
                        <!-- sslkey: The name of a file containing the private key used for the client SSL certificate -->
                        <!-- sslcrl: The name of a file containing the SSL certificate revocation list (CRL) -->
                        <!-- pooled: True to use a pooled server with the oci8/pdo_oracle driver -->
                        <!-- MultipleActiveResultSets: Configuring MultipleActiveResultSets for the pdo_sqlsrv driver -->
                        <doctrine:replica
                            name="replica1"
                            dbname=""
                            host="localhost"
                            port="null"
                            user="root"
                            password="null"
                            charset=""
                            dbname_suffix=""
                            path=""
                            memory=""
                            unix-socket=""
                            persistent=""
                            protocol=""
                            service=""
                            servicename=""
                            sessionMode=""
                            server=""
                            default_dbname=""
                            sslmode=""
                            sslrootcert=""
                            sslcert=""
                            sslkey=""
                            sslcrl=""
                            pooled=""
                            MultipleActiveResultSets=""
                        />

                    </doctrine:connection>

                </doctrine:dbal>

                <!-- auto-generate-proxy-classes: Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL, "FILE_NOT_EXISTS_OR_CHANGED" -->
                <doctrine:orm
                    default-entity-manager="default"
                    auto-generate-proxy-classes="false"
                    proxy-dir="%kernel.cache_dir%/doctrine/orm/Proxies"
                    proxy-namespace="Proxies"
                >

                    <!-- example -->
                    <doctrine:entity-manager
                        name="default"
                        connection=""
                        class-metadata-factory-name="Doctrine\ORM\Mapping\ClassMetadataFactory"
                        default-repository-class="Doctrine\ORM\EntityRepository"
                        auto-mapping="false"
                        report-fields-where-declared="false"
                        naming-strategy="doctrine.orm.naming_strategy.default"
                        quote-strategy="doctrine.orm.quote_strategy.default"
                        entity-listener-resolver="null"
                        repository-factory="null"
                    >

                        <doctrine:query-cache-driver
                            type="pool"
                            id=""
                            pool=""
                        />

                        <doctrine:metadata-cache-driver
                            type="pool"
                            id=""
                            pool=""
                        />

                        <doctrine:result-cache-driver
                            type="pool"
                            id=""
                            pool=""
                        />

                        <doctrine:entity-listeners>

                            <!-- example -->
                            <doctrine:entity class="Acme\HelloBundle\Entity\Author">

                                <!-- example -->
                                <doctrine:listener class="Acme\HelloBundle\EventListener\ExampleListener">

                                    <!-- example -->
                                    <doctrine:event
                                        type="preUpdate"
                                        method="preUpdate"
                                    />

                                </doctrine:listener>

                            </doctrine:entity>

                        </doctrine:entity-listeners>

                        <doctrine:second-level-cache
                            region-lock-lifetime="60"
                            log-enabled="true"
                            region-lifetime="0"
                            enabled="true"
                            factory=""
                        >

                            <doctrine:region-cache-driver
                                type="pool"
                                id=""
                                pool=""
                            />

                            <!-- example -->
                            <doctrine:region
                                name=""
                                lock-path="%kernel.cache_dir%/doctrine/orm/slc/filelock"
                                lock-lifetime="60"
                                type="default"
                                lifetime="0"
                                service=""
                            >

                                <doctrine:cache-driver
                                    type="pool"
                                    id=""
                                    pool=""
                                />

                            </doctrine:region>

                            <!-- example -->
                            <doctrine:logger
                                name=""
                                service=""
                            />

                        </doctrine:second-level-cache>

                        <!-- example -->
                        <doctrine:hydrator name="ListHydrator">Acme\HelloBundle\Hydrators\ListHydrator</doctrine:hydrator>

                        <!-- example -->
                        <doctrine:mapping
                            name="AcmeHelloBundle"
                            mapping="true"
                            type=""
                            dir=""
                            alias=""
                            prefix=""
                            is-bundle=""
                        />

                        <doctrine:dql>

                            <!-- example -->
                            <doctrine:string-function name="test_string">Acme\HelloBundle\DQL\StringFunction</doctrine:string-function>

                            <!-- example -->
                            <doctrine:numeric-function name="test_numeric">Acme\HelloBundle\DQL\NumericFunction</doctrine:numeric-function>

                            <!-- example -->
                            <doctrine:datetime-function name="test_datetime">Acme\HelloBundle\DQL\DatetimeFunction</doctrine:datetime-function>

                        </doctrine:dql>

                        <!-- example -->
                        <!-- Register SQL Filters in the entity manager -->
                        <!-- class: Required -->
                        <doctrine:filter
                            name="some_filter"
                            class="Acme\HelloBundle\Filter\SomeFilter"
                            enabled="false"
                        >

                            <!-- example -->
                            <doctrine:parameter name="foo_param">bar_value</doctrine:parameter>

                        </doctrine:filter>

                        <doctrine:schema-ignore-class>Acme\AppBundle\Entity\Order</doctrine:resolve-target-entity>
                        <doctrine:schema-ignore-class>Acme\AppBundle\Entity\PhoneNumber</doctrine:resolve-target-entity>
                    </doctrine:entity-manager>

                    <!-- example -->
                    <doctrine:resolve-target-entity interface="Acme\InvoiceBundle\Model\InvoiceSubjectInterface">Acme\AppBundle\Entity\Customer</doctrine:resolve-target-entity>

                </doctrine:orm>

            </doctrine:config>
        </container>

Configuration Overview
----------------------

This following configuration example shows all the configuration defaults that
the ORM resolves to:

.. code-block:: yaml

    doctrine:
        orm:
            auto_mapping: true
            # the standard distribution overrides this to be true in debug, false otherwise
            auto_generate_proxy_classes: false
            proxy_namespace: Proxies
            proxy_dir: "%kernel.cache_dir%/doctrine/orm/Proxies"
            default_entity_manager: default
            metadata_cache_driver: ~
            query_cache_driver: ~
            result_cache_driver: ~
            report_fields_where_declared: false

There are lots of other configuration options that you can use to overwrite
certain classes, but those are for very advanced use-cases only.

Oracle DB
~~~~~~~~~

If the environment format configured in oracle does not meet doctrine requirements,
you need to use the OracleSessionInit listener so that doctrine is aware of the format used by Oracle DB.

You can do so easily with

.. code-block:: yaml

    services:
        oracle.listener:
            class: Doctrine\DBAL\Event\Listeners\OracleSessionInit
            tags:
                - { name: doctrine.event_listener, event: postConnect }

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
    One of ``annotation``, ``xml``, ``yml``, ``php`` or ``staticphp``.
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

.. index::
    single: Configuration; Doctrine DBAL
    single: Doctrine; DBAL configuration

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

.. _doctrine filters: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/filters.html

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

.. note::

    When specifying a ``url`` parameter, any information extracted from that
    URL will override explicitly set parameters unless ``override_url`` is set
    to ``true``. An example database URL would be
    ``mysql://snoopy:redbaron@localhost/baseball``, and any explicitly set driver,
    user, password and dbname parameter would be overridden by this URL.
    See the Doctrine `DBAL documentation`_ for more information.

Besides default Doctrine options, there are some Symfony-related ones that you
can configure. The following block shows all possible configuration keys:

.. configuration-block::

    .. code-block:: yaml

        doctrine:
            dbal:
                url:                      mysql://user:secret@localhost:1234/otherdatabase # this would override the values below
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
                schema_filter:            ^sf2_
                mapping_types:
                    enum: string
                types:
                    custom: Acme\HelloBundle\MyCustomType
                default_table_options:
                    # Affects schema-tool. If absent, DBAL chooses defaults
                    # based on the platform. These defaults might be
                    # sub-optimal for backward compatibility reasons.
                    charset:              utf8mb4
                    collate:              utf8mb4_unicode_ci # when using doctrine/dbal 2.x
                    collation:            utf8mb4_unicode_ci # when using doctrine/dbal 3.x
                    engine:               InnoDB

    .. code-block:: xml

        <?xml version="1.0" encoding="UTF-8" ?>
        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:doctrine="http://symfony.com/schema/dic/doctrine"
            xsi:schemaLocation="http://symfony.com/schema/dic/services
                http://symfony.com/schema/dic/services/services-1.0.xsd
                http://symfony.com/schema/dic/doctrine
                http://symfony.com/schema/dic/doctrine/doctrine-1.0.xsd">

            <doctrine:config>
                <!--
                    SQLite specific options:
                    - path
                    - memory
                -->
                <!--
                    Oracle specific options:
                    - pooled (SERVER=POOLED)
                    - service (SERVICE_NAME instead of SID)
                    - servicename (SERVICE_NAME)
                -->
                <!--
                    PostgreSQL specific options:
                    - default_dbname (default_dbname)
                    - sslmode (LIBPQ-CONNECT-SSLMODE)
                    - sslrootcert (LIBPQ-CONNECT-SSLROOTCERT)
                    - sslcert (LIBPQ-CONNECT-SSLCERT)
                    - sslkey (LIBPQ-CONNECT-SSLKEY)
                    - sslcrl (LIBPQ-CONNECT-SSLCRL)
                -->
                <!--
                    IBM DB2 specific options:
                    - protocol (PROTOCOL)
                -->
                <!--
                    SQL Anywhere specific options:
                    - server (ServerName)
                -->
                <!--
                    oci8 specific options:
                    - sessionMode (session_mode)
                -->

                <doctrine:dbal
                    name="default"
                    url="mysql://user:secret@localhost:1234/otherdatabase"
                    dbname="database"
                    host="localhost"
                    port="1234"
                    user="user"
                    password="secret"
                    driver="pdo_mysql"
                    driver-class="MyNamespace\MyDriverImpl"
                    path="%kernel.project_dir%/var/data.db"
                    memory="true"
                    unix-socket="/tmp/mysql.sock"
                    persistent="true"
                    multiple-active-result-sets="true"
                    pooled="true"
                    protocol="TCPIP"
                    server="my_database_server"
                    service="true"
                    servicename="MyOracleServiceName"
                    sessionMode="2"
                    default_dbname="database"
                    sslmode="require"
                    sslrootcert="postgresql-ca.pem"
                    sslcert="postgresql-cert.pem"
                    sslkey="postgresql-key.pem"
                    sslcrl="postgresql.crl"
                    wrapper-class="MyDoctrineDbalConnectionWrapper"
                    charset=""
                    logging="%kernel.debug%"
                    platform-service="MyOwnDatabasePlatformService"
                    auto-commit="false"
                    schema-filter="^sf2_"
                >
                    <doctrine:option key="foo">bar</doctrine:option>
                    <doctrine:mapping-type name="enum">string</doctrine:mapping-type>
                    <doctrine:default-table-option name="charset">utf8mb4</doctrine:default-table-option>
                    <!-- when using doctrine/dbal 2.x -->
                    <doctrine:default-table-option name="collate">utf8mb4_unicode_ci</doctrine:default-table-option>
                    <!-- when using doctrine/dbal 3.x -->
                    <doctrine:default-table-option name="collation">utf8_unicode_ci</doctrine:default-table-option>
                    <doctrine:default-table-option name="engine">InnoDB</doctrine:default-table-option>
                    <doctrine:type name="custom">Acme\HelloBundle\MyCustomType</doctrine:type>
                </doctrine:dbal>
            </doctrine:config>
        </container>

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

.. _DBAL documentation: https://www.doctrine-project.org/projects/doctrine-dbal/en/2.10/index.html
