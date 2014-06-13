.. index::
   single: Doctrine; ORM Configuration Reference
   single: Configuration Reference; Doctrine ORM

Configuration Reference
=======================

.. configuration-block::

    .. code-block:: yaml

        doctrine:
            dbal:
                default_connection:   default
                connections:
                    default:
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
                        keep_slave:               true
                        charset:                  UTF8
                        logging:                  %kernel.debug%
                        platform_service:         MyOwnDatabasePlatformService
                        auto_commit:              false
                        schema_filter:            ^sf2_
                        mapping_types:
                            enum: string
                    conn1:
                        # ...
                types:
                    custom: Acme\HelloBundle\MyCustomType
            orm:
                auto_generate_proxy_classes:    false
                proxy_namespace:                Proxies
                proxy_dir:                      %kernel.cache_dir%/doctrine/orm/Proxies
                default_entity_manager:         default # The first defined is used if not set
                entity_managers:
                    default:
                        # The name of a DBAL connection (the one marked as default is used if not set)
                        connection:                     conn1
                        mappings: # Required
                            AcmeHelloBundle: ~
                        class_metadata_factory_name:    Doctrine\ORM\Mapping\ClassMetadataFactory
                        # All cache drivers have to be array, apc, xcache or memcache
                        metadata_cache_driver:          array
                        query_cache_driver:             array
                        result_cache_driver:
                            type:           memcache
                            host:           localhost
                            port:           11211
                            instance_class: Memcache
                            class:          Doctrine\Common\Cache\MemcacheCache
                        dql:
                            string_functions:
                                test_string: Acme\HelloBundle\DQL\StringFunction
                            numeric_functions:
                                test_numeric: Acme\HelloBundle\DQL\NumericFunction
                            datetime_functions:
                                test_datetime: Acme\HelloBundle\DQL\DatetimeFunction
                        naming_strategy:          doctrine.orm.naming_strategy.default          # Service Reference
                        entity_listener_resolver: doctrine.orm.entity_listener_resolver.default # Service reference
                    em2:
                        # ...

    .. code-block:: xml

        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:doctrine="http://symfony.com/schema/dic/doctrine"
            xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd
                                http://symfony.com/schema/dic/doctrine http://symfony.com/schema/dic/doctrine/doctrine-1.0.xsd">

            <doctrine:config>
                <doctrine:dbal default-connection="default">
                    <doctrine:connection
                        name="default"
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
                        keep-slave="true"
                        charset="UTF8"
                        logging="%kernel.debug%"
                        platform-service="MyOwnDatabasePlatformService"
                        auto-commit="false"
                        schema-filter="^sf2_"
                    >
                        <doctrine:option key="foo">bar</doctrine:option>
                        <doctrine:mapping-type name="enum">string</doctrine:mapping-type>
                    </doctrine:connection>
                    <doctrine:connection name="conn1" />
                    <doctrine:type name="custom">Acme\HelloBundle\MyCustomType</doctrine:type>
                </doctrine:dbal>

                <doctrine:orm default-entity-manager="default" auto-generate-proxy-classes="false" proxy-namespace="Proxies" proxy-dir="%kernel.cache_dir%/doctrine/orm/Proxies">
                    <doctrine:entity-manager name="default" query-cache-driver="array" result-cache-driver="array" connection="conn1" class-metadata-factory-name="Doctrine\ORM\Mapping\ClassMetadataFactory" naming-strategy="doctrine.orm.naming_strategy.default">
                        <doctrine:metadata-cache-driver type="memcache" host="localhost" port="11211" instance-class="Memcache" class="Doctrine\Common\Cache\MemcacheCache" />
                        <doctrine:mapping name="AcmeHelloBundle" />
                        <doctrine:dql>
                            <doctrine:string-function name="test_string>Acme\HelloBundle\DQL\StringFunction</doctrine:string-function>
                            <doctrine:numeric-function name="test_numeric>Acme\HelloBundle\DQL\NumericFunction</doctrine:numeric-function>
                            <doctrine:datetime-function name="test_datetime>Acme\HelloBundle\DQL\DatetimeFunction</doctrine:datetime-function>
                        </doctrine:dql>
                    </doctrine:entity-manager>
                    <doctrine:entity-manager name="em2" connection="conn2" metadata-cache-driver="apc">
                        <doctrine:mapping
                            name="DoctrineExtensions"
                            type="xml"
                            dir="%kernel.root_dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entity"
                            prefix="DoctrineExtensions\Entity"
                            alias="DExt"
                        />
                    </doctrine:entity-manager>
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
            proxy_dir: %kernel.cache_dir%/doctrine/orm/Proxies
            default_entity_manager: default
            metadata_cache_driver: array
            query_cache_driver: array
            result_cache_driver: array

There are lots of other configuration options that you can use to overwrite
certain classes, but those are for very advanced use-cases only.

Caching Drivers
~~~~~~~~~~~~~~~

For the caching drivers you can specify the values "array", "apc", "memcache", "memcached"
or "xcache".

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

* ``type`` One of ``annotation``, ``xml``, ``yml``, ``php`` or ``staticphp``.
  This specifies which type of metadata type your mapping uses.

* ``dir`` Path to the mapping or entity files (depending on the driver). If
  this path is relative it is assumed to be relative to the bundle root. This
  only works if the name of your mapping is a bundle name. If you want to use
  this option to specify absolute paths you should prefix the path with the
  kernel parameters that exist in the DIC (for example %kernel.root_dir%).

* ``prefix`` A common namespace prefix that all entities of this mapping
  share. This prefix should never conflict with prefixes of other defined
  mappings otherwise some of your entities cannot be found by Doctrine. This
  option defaults to the bundle namespace + ``Entity``, for example for an
  application bundle called ``AcmeHelloBundle`` prefix would be
  ``Acme\HelloBundle\Entity``.

* ``alias`` Doctrine offers a way to alias entity namespaces to simpler,
  shorter names to be used in DQL queries or for Repository access. When using
  a bundle the alias defaults to the bundle name.

* ``is_bundle`` This option is a derived value from ``dir`` and by default is
  set to true if dir is relative proved by a ``file_exists()`` check that
  returns false. It is false if the existence check returns true. In this case
  an absolute path was specified and the metadata files are most likely in a
  directory outside of a bundle.

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

* ``myFilter:``   Filter identifier (Required)
* ``class:``      Filter target class (Required)
* ``enabled:``    Enable/Disable the filter by default (Optional - Default disabled)
* ``parameters:`` Set default parameters (Optional)
* ``myParameter: myValue`` Bind the value ``myValue`` to the parameter ``myParameter`` (Optional)

.. _doctrine filters: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/filters.html

.. _`reference-dbal-configuration`:

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
