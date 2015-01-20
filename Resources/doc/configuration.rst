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
                        commented:            true

                connections:
                    # A collection of different named connections (e.g. default, conn2, etc)
                    default:
                        dbname:               ~
                        host:                 localhost
                        port:                 ~
                        user:                 root
                        password:             ~
                        charset:              "UTF8"

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

                        # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                        # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                        sslmode:              ~

                        # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                        pooled:               ~

                        # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                        MultipleActiveResultSets:  ~
                        driver:               pdo_mysql
                        platform_service:     ~
                        auto_commit:          ~

                        # If set to "^sf2_" all tables not prefixed with "sf2_" will be ignored by the schema
                        # tool. This is for custom tables which should not be altered automatically.
                        schema_filter:        ~

                        # When true, queries are logged to a "doctrine" monolog channel
                        logging:              "%kernel.debug%"

                        profiling:            "%kernel.debug%"
                        server_version:       ~
                        driver_class:         ~
                        wrapper_class:        ~
                        shard_choser:         ~
                        shard_choser_service: ~
                        keep_slave:           ~

                        # An array of options
                        options:
                            # example
                            # key:                  value

                        # An array of mapping types
                        mapping_types:
                            # example
                            # enum:                 string

                        slaves:
                            # A collection of named slave connections (e.g. slave1, slave2)
                            slave1:
                                dbname:               ~
                                host:                 localhost
                                port:                 ~
                                user:                 root
                                password:             ~
                                charset:              ~
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

                                # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                                # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                                sslmode:              ~

                                # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                                pooled:               ~

                                # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                                MultipleActiveResultSets:  ~

                        shards:
                            id:                   ~ # Required
                            dbname:               ~
                            host:                 localhost
                            port:                 ~
                            user:                 root
                            password:             ~
                            charset:              ~
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

                            # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                            # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                            sslmode:              ~

                            # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                            pooled:               ~

                            # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                            MultipleActiveResultSets:  ~

            orm:
                default_entity_manager: ~ # The first defined is used if not set

                # Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL"
                auto_generate_proxy_classes:  false
                proxy_dir:                    "%kernel.cache_dir%/doctrine/orm/Proxies"
                proxy_namespace:              Proxies

                entity_managers:

                    # A collection of different named entity managers (e.g. some_em, another_em)
                    some_em:
                        query_cache_driver:
                            type:                 array
                            host:                 ~
                            port:                 ~
                            instance_class:       ~
                            class:                ~
                            id:                   ~
                            namespace:            ~
                            cache_provider:       ~
                        metadata_cache_driver:
                            type:                 array
                            host:                 ~
                            port:                 ~
                            instance_class:       ~
                            class:                ~
                            id:                   ~
                            namespace:            ~
                            cache_provider:       ~
                        result_cache_driver:
                            type:                 array
                            host:                 ~
                            port:                 ~
                            instance_class:       ~
                            class:                ~
                            id:                   ~
                            namespace:            ~
                            cache_provider:       ~
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
                        naming_strategy:              doctrine.orm.naming_strategy.default
                        entity_listener_resolver:     ~
                        repository_factory:           ~
                        second_level_cache:
                            region_cache_driver:
                                type:                 array
                                host:                 ~
                                port:                 ~
                                instance_class:       ~
                                class:                ~
                                id:                   ~
                                namespace:            ~
                                cache_provider:       ~
                            region_lock_lifetime: 60
                            log_enabled:          true
                            region_lifetime:      0
                            enabled:              true
                            factory:              ~
                            regions:

                                # Prototype
                                name:
                                    cache_driver:
                                        type:                 array
                                        host:                 ~
                                        port:                 ~
                                        instance_class:       ~
                                        class:                ~
                                        id:                   ~
                                        namespace:            ~
                                        cache_provider:       ~
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
                        commented="true"
                    />

                    <!-- example -->
                    <!-- unix-socket: The unix socket to use for MySQL -->
                    <!-- persistent: True to use as persistent connection for the ibm_db2 driver -->
                    <!-- protocol: The protocol to use for the ibm_db2 driver (default to TCPIP if omitted) -->
                    <!-- service: True to use SERVICE_NAME as connection parameter instead of SID for Oracle -->
                    <!-- servicename: Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter. -->
                    <!-- sessionMode: The session mode to use for the oci8 driver -->
                    <!-- server: The name of a running database server to connect to for SQL Anywhere. -->
                    <!-- sslmode: Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL. -->
                    <!-- pooled: True to use a pooled server with the oci8/pdo_oracle driver -->
                    <!-- MultipleActiveResultSets: Configuring MultipleActiveResultSets for the pdo_sqlsrv driver -->
                    <doctrine:connection
                        name="default"
                        dbname=""
                        host="localhost"
                        port="null"
                        user="root"
                        password="null"
                        charset="UTF8"
                        path=""
                        memory=""
                        unix-socket=""
                        persistent=""
                        protocol=""
                        service=""
                        servicename=""
                        sessionMode=""
                        server=""
                        sslmode=""
                        pooled=""
                        MultipleActiveResultSets=""
                        driver="pdo_mysql"
                        platform-service=""
                        auto-commit=""
                        schema-filter=""
                        logging="%kernel.debug%"
                        profiling="%kernel.debug%"
                        server-version=""
                        driver-class=""
                        wrapper-class=""
                        shard-choser=""
                        shard-choser-service=""
                        keep-slave=""
                    >

                        <!-- example -->
                        <doctrine:option key="key">value</doctrine:option>

                        <!-- example -->
                        <doctrine:mapping-type name="enum">string</doctrine:mapping-type>

                        <!-- example -->
                        <!-- unix-socket: The unix socket to use for MySQL -->
                        <!-- persistent: True to use as persistent connection for the ibm_db2 driver -->
                        <!-- protocol: The protocol to use for the ibm_db2 driver (default to TCPIP if omitted) -->
                        <!-- service: True to use SERVICE_NAME as connection parameter instead of SID for Oracle -->
                        <!-- servicename: Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter. -->
                        <!-- sessionMode: The session mode to use for the oci8 driver -->
                        <!-- server: The name of a running database server to connect to for SQL Anywhere. -->
                        <!-- sslmode: Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL. -->
                        <!-- pooled: True to use a pooled server with the oci8/pdo_oracle driver -->
                        <!-- MultipleActiveResultSets: Configuring MultipleActiveResultSets for the pdo_sqlsrv driver -->
                        <doctrine:slave
                            name="slave1"
                            dbname=""
                            host="localhost"
                            port="null"
                            user="root"
                            password="null"
                            charset=""
                            path=""
                            memory=""
                            unix-socket=""
                            persistent=""
                            protocol=""
                            service=""
                            servicename=""
                            sessionMode=""
                            server=""
                            sslmode=""
                            pooled=""
                            MultipleActiveResultSets=""
                        />

                        <!-- example -->
                        <!-- id: Required -->
                        <!-- unix-socket: The unix socket to use for MySQL -->
                        <!-- persistent: True to use as persistent connection for the ibm_db2 driver -->
                        <!-- protocol: The protocol to use for the ibm_db2 driver (default to TCPIP if omitted) -->
                        <!-- service: True to use SERVICE_NAME as connection parameter instead of SID for Oracle -->
                        <!-- servicename: Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter. -->
                        <!-- sessionMode: The session mode to use for the oci8 driver -->
                        <!-- server: The name of a running database server to connect to for SQL Anywhere. -->
                        <!-- sslmode: Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL. -->
                        <!-- pooled: True to use a pooled server with the oci8/pdo_oracle driver -->
                        <!-- MultipleActiveResultSets: Configuring MultipleActiveResultSets for the pdo_sqlsrv driver -->
                        <doctrine:shard
                            id=""
                            dbname=""
                            host="localhost"
                            port="null"
                            user="root"
                            password="null"
                            charset=""
                            path=""
                            memory=""
                            unix-socket=""
                            persistent=""
                            protocol=""
                            service=""
                            servicename=""
                            sessionMode=""
                            server=""
                            sslmode=""
                            pooled=""
                            MultipleActiveResultSets=""
                        />

                    </doctrine:connection>

                </doctrine:dbal>

                <!-- auto-generate-proxy-classes: Auto generate mode possible values are: "NEVER", "ALWAYS", "FILE_NOT_EXISTS", "EVAL" -->
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
                        naming-strategy="doctrine.orm.naming_strategy.default"
                        entity-listener-resolver="null"
                        repository-factory="null"
                    >

                        <doctrine:query-cache-driver
                            type="array"
                            host=""
                            port=""
                            instance-class=""
                            class=""
                            id=""
                            namespace="null"
                            cache-provider="null"
                        />

                        <doctrine:metadata-cache-driver
                            type="memcache"
                            host="localhost"
                            port="11211"
                            instance-class="Memcache"
                            class="Doctrine\Common\Cache\MemcacheCache"
                            id=""
                            namespace="null"
                            cache-provider="null"
                        />

                        <doctrine:result-cache-driver
                            type="array"
                            host=""
                            port=""
                            instance-class=""
                            class=""
                            id=""
                            namespace="null"
                            cache-provider="null"
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
                                type="array"
                                host=""
                                port=""
                                instance-class=""
                                class=""
                                id=""
                                namespace="null"
                                cache-provider="null"
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
                                    type="array"
                                    host=""
                                    port=""
                                    instance-class=""
                                    class=""
                                    id=""
                                    namespace="null"
                                    cache-provider="null"
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
            metadata_cache_driver: array
            query_cache_driver: array
            result_cache_driver: array

There are lots of other configuration options that you can use to overwrite
certain classes, but those are for very advanced use-cases only.

Caching Drivers
~~~~~~~~~~~~~~~

For the caching drivers you can specify the values ``array``, ``apc``, ``memcache``,
``memcached`` or ``xcache``.

The following example shows an overview of the caching configurations:

.. code-block:: yaml

    doctrine:
        orm:
            auto_mapping: true
            metadata_cache_driver: apc
            query_cache_driver: xcache
            result_cache_driver:
                type: memcache
                host: localhost
                port: 11211
                instance_class: Memcache

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

Doctrine DBAL Configuration
---------------------------

.. note::

    DoctrineBundle supports all parameters that default Doctrine drivers
    accept, converted to the XML or YAML naming standards that Symfony
    enforces. See the Doctrine `DBAL documentation`_ for more information.

.. note::

    When specifying a ``url`` parameter, any information extracted from that
    URL will override explicitly set parameters. An example database URL
    would be ``mysql://snoopy:redbaron@localhost/baseball``, and any explicitly
    set driver, user, password and dbname parameter would be overridden by
    this URL. See the Doctrine `DBAL documentation`_ for more information.

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
                driver:                   pdo_mysql
                driver_class:             MyNamespace\MyDriverImpl
                options:
                    foo: bar
                path:                     %kernel.data_dir%/data.sqlite # SQLite specific
                memory:                   true                          # SQLite specific
                unix_socket:              /tmp/mysql.sock
                persistent:               true
                MultipleActiveResultSets: true                # pdo_sqlsrv driver specific
                pooled:                   true                # Oracle specific (SERVER=POOLED)
                protocol:                 TCPIP               # IBM DB2 specific (PROTOCOL)
                server:                   my_database_server  # SQL Anywhere specific (ServerName)
                service:                  true                # Oracle specific (SERVICE_NAME instead of SID)
                servicename:              MyOracleServiceName # Oracle specific (SERVICE_NAME)
                sessionMode:              2                   # oci8 driver specific (session_mode)
                sslmode:                  require             # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE)
                wrapper_class:            MyDoctrineDbalConnectionWrapper
                charset:                  UTF8
                logging:                  %kernel.debug%
                platform_service:         MyOwnDatabasePlatformService
                auto_commit:              false
                schema_filter:            ^sf2_
                mapping_types:
                    enum: string
                types:
                    custom: Acme\HelloBundle\MyCustomType

    .. code-block:: xml

        <!-- xmlns:doctrine="http://symfony.com/schema/dic/doctrine" -->
        <!-- xsi:schemaLocation="http://symfony.com/schema/dic/doctrine http://symfony.com/schema/dic/doctrine/doctrine-1.0.xsd"> -->

        <doctrine:config>
            <doctrine:dbal
                name="default"
                url="mysql://user:secret@localhost:1234/otherdatabase" <!-- this would override the values below -->
                dbname="database"
                host="localhost"
                port="1234"
                user="user"
                password="secret"
                driver="pdo_mysql"
                driver-class="MyNamespace\MyDriverImpl"
                path="%kernel.data_dir%/data.sqlite" <!-- SQLite specific -->
                memory="true"                        <!-- SQLite specific -->
                unix-socket="/tmp/mysql.sock"
                persistent="true"
                multiple-active-result-sets="true" <!-- pdo_sqlsrv driver specific -->
                pooled="true"                      <!-- Oracle specific (SERVER=POOLED) -->
                protocol="TCPIP"                   <!-- IBM DB2 specific (PROTOCOL) -->
                server="my_database_server"        <!-- SQL Anywhere specific (ServerName) -->
                service="true"                     <!-- Oracle specific (SERVICE_NAME instead of SID) -->
                servicename="MyOracleServiceName"  <!-- Oracle specific (SERVICE_NAME) -->
                sessionMode"2"                     <!-- oci8 driver specific (session_mode) -->
                sslmode="require"                  <!-- PostgreSQL specific (LIBPQ-CONNECT-SSLMODE) -->
                wrapper-class="MyDoctrineDbalConnectionWrapper"
                charset="UTF8"
                logging="%kernel.debug%"
                platform-service="MyOwnDatabasePlatformService"
                auto-commit="false"
                schema-filter="^sf2_"
            >
                <doctrine:option key="foo">bar</doctrine:option>
                <doctrine:mapping-type name="enum">string</doctrine:mapping-type>
                <doctrine:type name="custom">Acme\HelloBundle\MyCustomType</doctrine:type>
            </doctrine:dbal>
        </doctrine:config>

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
service where ``[name]`` if the name of the connection.

.. _DBAL documentation: http://www.doctrine-project.org/docs/dbal/2.0/en
