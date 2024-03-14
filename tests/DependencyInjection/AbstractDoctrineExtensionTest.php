<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DbalSchemaFilterPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\WellKnownSchemaFilterPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\InvokableEntityListener;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Schema\LegacySchemaManagerFactory;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Generator;
use InvalidArgumentException;
use LogicException;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestHydrator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_filter;
use function array_intersect_key;
use function array_keys;
use function array_merge;
use function array_values;
use function assert;
use function class_exists;
use function end;
use function interface_exists;
use function is_dir;
use function method_exists;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function version_compare;

use const DIRECTORY_SEPARATOR;

abstract class AbstractDoctrineExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, string $file): void;

    public function testDbalLoadFromXmlMultipleConnections(): void
    {
        $container = $this->loadContainer('dbal_service_multiple_connections');

        // doctrine.dbal.mysql_connection
        $config = $container->getDefinition('doctrine.dbal.mysql_connection')->getArgument(0);

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);

        // doctrine.dbal.sqlite_connection
        $config = $container->getDefinition('doctrine.dbal.sqlite_connection')->getArgument(0);
        $this->assertSame('pdo_sqlite', $config['driver']);
        $this->assertSame('sqlite_db', $config['dbname']);
        $this->assertSame('sqlite_user', $config['user']);
        $this->assertSame('sqlite_s3cr3t', $config['password']);
        $this->assertSame('/tmp/db.sqlite', $config['path']);
        $this->assertTrue($config['memory']);
        $this->assertSame(['asin' => ['callback' => 'asin', 'numArgs' => 1]], $config['driverOptions']['userDefinedFunctions']);
        $this->assertSame('foo', $config['driverOptions']['arbitraryValue']);

        // doctrine.dbal.oci8_connection
        $config = $container->getDefinition('doctrine.dbal.oci_connection')->getArgument(0);
        $this->assertSame('oci8', $config['driver']);
        $this->assertSame('oracle_db', $config['dbname']);
        $this->assertSame('oracle_user', $config['user']);
        $this->assertSame('oracle_s3cr3t', $config['password']);
        $this->assertSame('oracle_service', $config['servicename']);
        $this->assertTrue($config['service']);
        $this->assertTrue($config['pooled']);
        $this->assertSame('utf8', $config['charset']);

        // doctrine.dbal.ibmdb2_connection
        $config = $container->getDefinition('doctrine.dbal.ibmdb2_connection')->getArgument(0);
        $this->assertSame('ibm_db2', $config['driver']);
        $this->assertSame('ibmdb2_db', $config['dbname']);
        $this->assertSame('ibmdb2_user', $config['user']);
        $this->assertSame('ibmdb2_s3cr3t', $config['password']);
        $this->assertSame('TCPIP', $config['protocol']);

        // doctrine.dbal.pgsql_connection
        $config = $container->getDefinition('doctrine.dbal.pgsql_connection')->getArgument(0);
        $this->assertSame('pdo_pgsql', $config['driver']);
        $this->assertSame('pgsql_schema', $config['dbname']);
        $this->assertSame('pgsql_user', $config['user']);
        $this->assertSame('pgsql_s3cr3t', $config['password']);
        $this->assertSame('pgsql_db', $config['default_dbname']);
        $this->assertSame('require', $config['sslmode']);
        $this->assertSame('postgresql-ca.pem', $config['sslrootcert']);
        $this->assertSame('postgresql-cert.pem', $config['sslcert']);
        $this->assertSame('postgresql-key.pem', $config['sslkey']);
        $this->assertSame('postgresql.crl', $config['sslcrl']);
        $this->assertSame('utf8', $config['charset']);

        // doctrine.dbal.sqlanywhere_connection
        $config = $container->getDefinition('doctrine.dbal.sqlanywhere_connection')->getArgument(0);
        $this->assertSame('sqlanywhere', $config['driver']);
        $this->assertSame('localhost', $config['host']);
        $this->assertSame(2683, $config['port']);
        $this->assertSame('sqlanywhere_server', $config['server']);
        $this->assertSame('sqlanywhere_db', $config['dbname']);
        $this->assertSame('sqlanywhere_user', $config['user']);
        $this->assertSame('sqlanywhere_s3cr3t', $config['password']);
        $this->assertTrue($config['persistent']);
        $this->assertSame('utf8', $config['charset']);
    }

    public function testDbalLoadFromXmlSingleConnections(): void
    {
        $container = $this->loadContainer('dbal_service_single_connection');
        $config    = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
        $this->assertEquals('5.6.20', $config['serverVersion']);
    }

    /** @group legacy */
    public function testDbalLoadUrlOverride(): void
    {
        $container = $this->loadContainer('dbal_allow_url_override');
        $config    = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertSame('mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8', $config['url']);

        $expectedOverrides = [
            'dbname' => 'main_test',
            'user' => 'tester',
            'password' => 'wordpass',
            'host' => 'localhost',
            'port' => 4321,
        ];

        $this->assertEquals($expectedOverrides, array_intersect_key($config, $expectedOverrides));
        $this->assertSame($expectedOverrides, $config['connection_override_options']);
        $this->assertFalse(isset($config['override_url']));
    }

    /** @group legacy */
    public function testDbalLoadPartialUrlOverrideSetsDefaults(): void
    {
        $container = $this->loadContainer('dbal_allow_partial_url_override');
        $config    = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $expectedDefaults = [
            'host' => 'localhost',
            'user' => 'root',
            'password' => null,
            'port' => null,
        ];

        $this->assertEquals($expectedDefaults, array_intersect_key($config, $expectedDefaults));
        $this->assertSame('mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8', $config['url']);
        $this->assertCount(1, $config['connection_override_options']);
        $this->assertSame('main_test', $config['connection_override_options']['dbname']);
        $this->assertFalse(isset($config['override_url']));
    }

    public function testDbalDbnameSuffix(): void
    {
        $container = $this->loadContainer('dbal_dbname_suffix');
        $config    = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertSame('mysql://root:password@database:3306/main?serverVersion=mariadb-10.5.8', $config['url']);
        $this->assertSame('_test', $config['dbname_suffix']);
    }

    public function testDbalDriverScheme(): void
    {
        $container = $this->loadContainer('dbal_driver_schemes');
        $schemes   = $container->getDefinition('doctrine.dbal.connection_factory.dsn_parser')->getArgument(0);

        $this->assertSame('my_driver', $schemes['my-scheme']);
        $this->assertSame('pgsql', $schemes['postgresql'], 'Overriding a default mapping should be supported.');
        $this->assertSame('pdo_mysql', $schemes['mysql']);
    }

    public function testDbalLoadSinglePrimaryReplicaConnection(): void
    {
        $container = $this->loadContainer('dbal_service_single_primary_replica_connection');
        $param     = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals(PrimaryReadReplicaConnection::class, $param['wrapperClass']);
        $this->assertTrue($param['keepReplica']);
        $this->assertEquals(
            [
                'user' => 'mysql_user',
                'password' => 'mysql_s3cr3t',
                'port' => null,
                'dbname' => 'mysql_db',
                'host' => 'localhost',
                'unix_socket' => '/path/to/mysqld.sock',
                'driverOptions' => [PDO::ATTR_STRINGIFY_FETCHES => 1],
            ],
            $param['primary'],
        );
        $this->assertEquals(
            [
                'user' => 'replica_user',
                'password' => 'replica_s3cr3t',
                'port' => null,
                'dbname' => 'replica_db',
                'host' => 'localhost',
                'unix_socket' => '/path/to/mysqld_replica.sock',
                'driverOptions' => [PDO::ATTR_STRINGIFY_FETCHES => 1],
            ],
            $param['replica']['replica1'],
        );
        $this->assertEquals(['engine' => 'InnoDB'], $param['defaultTableOptions']);
    }

    public function testDbalLoadSavepointsForNestedTransactions(): void
    {
        $container = $this->loadContainer('dbal_savepoints');

        $calls = $container->getDefinition('doctrine.dbal.savepoints_connection')->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('setNestTransactionsWithSavepoints', $calls[0][0]);
        $this->assertTrue($calls[0][1][0]);

        $calls = $container->getDefinition('doctrine.dbal.nosavepoints_connection')->getMethodCalls();
        $this->assertCount(0, $calls);

        $calls = $container->getDefinition('doctrine.dbal.notset_connection')->getMethodCalls();
        $this->assertCount(0, $calls);
    }

    public function testDbalLoadDisableTypeComments(): void
    {
        $container = $this->loadContainer('dbal_disable_type_comments');

        $calls = $container->getDefinition('doctrine.dbal.no_comments_connection.configuration')->getMethodCalls();
        $calls = array_values(array_filter($calls, static fn ($call) => $call[0] === 'setDisableTypeComments'));
        $this->assertCount(1, $calls);
        $this->assertEquals('setDisableTypeComments', $calls[0][0]);
        $this->assertTrue($calls[0][1][0]);

        $calls = $container->getDefinition('doctrine.dbal.comments_connection.configuration')->getMethodCalls();
        $calls = array_values(array_filter($calls, static fn ($call) => $call[0] === 'setDisableTypeComments'));
        $this->assertCount(1, $calls);
        $this->assertFalse($calls[0][1][0]);

        $calls = $container->getDefinition('doctrine.dbal.notset_connection.configuration')->getMethodCalls();
        $calls = array_values(array_filter($calls, static fn ($call) => $call[0] === 'setDisableTypeComments'));
        $this->assertCount(0, $calls);
    }

    /** @group legacy */
    public function testDbalSchemaManagerFactory(): void
    {
        $container = $this->loadContainer('dbal_schema_manager_factory');

        $this->assertDICDefinitionMethodCallOnce(
            $container->getDefinition('doctrine.dbal.default_schema_manager_factory_connection.configuration'),
            'setSchemaManagerFactory',
            [
                new Reference(class_exists(LegacySchemaManagerFactory::class)
                ? 'doctrine.dbal.legacy_schema_manager_factory'
                : 'doctrine.dbal.default_schema_manager_factory'),
            ],
        );

        $this->assertDICDefinitionMethodCallOnce(
            $container->getDefinition('doctrine.dbal.custom_schema_manager_factory_connection.configuration'),
            'setSchemaManagerFactory',
            [new Reference('custom_factory')],
        );
    }

    public function testDbalResultCache(): void
    {
        $container = $this->loadContainer('dbal_result_cache');

        $this->assertDICDefinitionMethodCallOnce(
            $container->getDefinition('doctrine.dbal.connection_with_cache_connection.configuration'),
            'setResultCache',
            [
                new Reference('example.cache'),
            ],
        );

        $this->assertDICDefinitionMethodCallCount(
            $container->getDefinition('doctrine.dbal.connection_without_cache_connection.configuration'),
            'setResultCache',
            [],
            0,
        );
    }

    public function testLoadSimpleSingleConnection(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_service_simple_single_entity_manager');

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, [
            [
                'dbname' => 'db',
                'host' => 'localhost',
                'port' => null,
                'user' => 'root',
                'password' => null,
                'driver' => 'pdo_mysql',
                'driverOptions' => [],
                'defaultTableOptions' => [],
            ],
            new Reference('doctrine.dbal.default_connection.configuration'),
            method_exists(Connection::class, 'getEventManager')
                ? new Reference('doctrine.dbal.default_connection.event_manager')
                : null,
            [],
        ]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertNull($definition->getFactory());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);
    }

    /**
     * The PDO driver doesn't require a database name to be to set when connecting to a database server
     */
    public function testLoadSimpleSingleConnectionWithoutDbName(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_service_simple_single_entity_manager_without_dbname');

        $this->assertDICConstructorArguments($container->getDefinition('doctrine.dbal.default_connection'), [
            [
                'host' => 'localhost',
                'port' => null,
                'user' => 'root',
                'password' => null,
                'driver' => 'pdo_mysql',
                'driverOptions' => [],
                'defaultTableOptions' => [],
            ],
            new Reference('doctrine.dbal.default_connection.configuration'),
            method_exists(Connection::class, 'getEventManager')
                ? new Reference('doctrine.dbal.default_connection.event_manager')
                : null,
            [],
        ]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);
    }

    public function testLoadSingleConnection(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_service_single_entity_manager');

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, [
            [
                'host' => 'localhost',
                'driver' => 'pdo_sqlite',
                'driverOptions' => [],
                'user' => 'sqlite_user',
                'port' => null,
                'password' => 'sqlite_s3cr3t',
                'dbname' => 'sqlite_db',
                'memory' => true,
                'defaultTableOptions' => [],
            ],
            new Reference('doctrine.dbal.default_connection.configuration'),
            method_exists(Connection::class, 'getEventManager')
                ? new Reference('doctrine.dbal.default_connection.event_manager')
                : null,
            [],
        ]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);

        $configDef = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setDefaultRepositoryClassName', ['Acme\Doctrine\Repository']);
    }

    public function testLoadMultipleConnections(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_service_multiple_entity_managers');

        $definition = $container->getDefinition('doctrine.dbal.conn1_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn1_connection.configuration', (string) $args[1]);
        if (method_exists(Connection::class, 'getEventManager')) {
            $this->assertEquals('doctrine.dbal.conn1_connection.event_manager', (string) $args[2]);
        }

        $this->assertEquals('doctrine.orm.em2_entity_manager', (string) $container->getAlias('doctrine.orm.entity_manager'));

        $definition = $container->getDefinition('doctrine.orm.em1_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertEquals('doctrine.orm.em1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.dbal.conn2_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn2_connection.configuration', (string) $args[1]);
        if (method_exists(Connection::class, 'getEventManager')) {
            $this->assertEquals('doctrine.dbal.conn2_connection.event_manager', (string) $args[2]);
        }

        $definition = $container->getDefinition('doctrine.orm.em2_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertEquals('doctrine.orm.em2_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.em1_metadata_cache'));
        $this->assertEquals(PhpArrayAdapter::class, $definition->getClass());

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.em1_query_cache'));
        $this->assertSame(ArrayAdapter::class, $definition->getClass());

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.em1_result_cache'));
        $this->assertSame(ArrayAdapter::class, $definition->getClass());
    }

    public function testEntityManagerMetadataCacheDriverConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_service_multiple_entity_managers');

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.em1_metadata_cache'));
        $this->assertDICDefinitionClass($definition, PhpArrayAdapter::class);

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.em2_metadata_cache'));
        $this->assertDICDefinitionClass($definition, PhpArrayAdapter::class);
    }

    public function testDependencyInjectionImportsOverrideDefaults(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_imports');

        $configDefinition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDefinition, 'setAutoGenerateProxyClasses', ['%doctrine.orm.auto_generate_proxy_classes%']);

        $cacheDefinition = $container->getDefinition((string) $container->getAlias('doctrine.orm.default_metadata_cache'));
        $this->assertEquals(PhpArrayAdapter::class, $cacheDefinition->getClass());
        $this->assertDICDefinitionMethodCallOnce($configDefinition, 'setMetadataCache', [new Reference('doctrine.orm.default_metadata_cache')]);
    }

    /** @requires PHP 8 */
    public function testSingleEntityManagerMultipleMappingBundleDefinitions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_single_em_bundle_mappings', ['YamlBundle', 'AnnotationsBundle', 'XmlBundle', 'AttributesBundle']);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');

        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', [
            new Reference(version_compare(Kernel::VERSION, '7.0.0', '<') ? 'doctrine.orm.default_annotation_metadata_driver' : 'doctrine.orm.default_attribute_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', [
            new Reference('doctrine.orm.default_attribute_metadata_driver'),
            'Fixtures\Bundles\AttributesBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(2, $definition, 'addDriver', [
            new Reference('doctrine.orm.default_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(3, $definition, 'addDriver', [
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle',
        ]);

        $attrDef = $container->getDefinition('doctrine.orm.default_attribute_metadata_driver');
        $this->assertDICConstructorArguments($attrDef, [
            array_merge(
                ! version_compare(Kernel::VERSION, '7.0.0', '<') ? [
                    __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'AnnotationsBundle' . DIRECTORY_SEPARATOR . 'Entity',
                ] : [],
                [
                    __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'AttributesBundle' . DIRECTORY_SEPARATOR . 'Entity',
                ],
            ),
            ! class_exists(AnnotationDriver::class),
        ]);

        $ymlDef = $container->getDefinition('doctrine.orm.default_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, [
            [__DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'YamlBundle' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine' => 'Fixtures\Bundles\YamlBundle\Entity'],
        ]);

        $xmlDef = $container->getDefinition('doctrine.orm.default_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, [
            [__DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'XmlBundle' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine' => 'Fixtures\Bundles\XmlBundle'],
            SimplifiedXmlDriver::DEFAULT_FILE_EXTENSION,
            true,
        ]);
    }

    /** @requires PHP 8 */
    public function testMultipleEntityManagersMappingBundleDefinitions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_multiple_em_bundle_mappings', ['YamlBundle', 'AnnotationsBundle', 'XmlBundle', 'AttributesBundle']);

        $this->assertEquals(['em1' => 'doctrine.orm.em1_entity_manager', 'em2' => 'doctrine.orm.em2_entity_manager'], $container->getParameter('doctrine.entity_managers'), 'Set of the existing EntityManagers names is incorrect.');
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), 'Set of the existing EntityManagers names is incorrect.');

        $def1   = $container->getDefinition('doctrine.orm.em1_metadata_driver');
        $def2   = $container->getDefinition('doctrine.orm.em2_metadata_driver');
        $def1Id = version_compare(Kernel::VERSION, '7.0.0', '<') ? 'doctrine.orm.em1_annotation_metadata_driver' : 'doctrine.orm.em1_attribute_metadata_driver';

        $this->assertDICDefinitionMethodCallAt(0, $def1, 'addDriver', [
            new Reference($def1Id),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(1, $def1, 'addDriver', [
            new Reference('doctrine.orm.em1_attribute_metadata_driver'),
            'Fixtures\Bundles\AttributesBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(0, $def2, 'addDriver', [
            new Reference('doctrine.orm.em2_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity',
        ]);

        $this->assertDICDefinitionMethodCallAt(1, $def2, 'addDriver', [
            new Reference('doctrine.orm.em2_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle',
        ]);

        if (version_compare(Kernel::VERSION, '7.0.0', '<')) {
            $annDef = $container->getDefinition($def1Id);
            $this->assertDICConstructorArguments($annDef, [
                new Reference('doctrine.orm.metadata.annotation_reader'),
                [
                    __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'AnnotationsBundle' . DIRECTORY_SEPARATOR . 'Entity',
                ],
                ! class_exists(AnnotationDriver::class),
            ]);
        }

        $ymlDef = $container->getDefinition('doctrine.orm.em2_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, [
            [__DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'YamlBundle' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine' => 'Fixtures\Bundles\YamlBundle\Entity'],
        ]);

        $xmlDef = $container->getDefinition('doctrine.orm.em2_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, [
            [__DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Bundles' . DIRECTORY_SEPARATOR . 'XmlBundle' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine' => 'Fixtures\Bundles\XmlBundle'],
            SimplifiedXmlDriver::DEFAULT_FILE_EXTENSION,
            true,
        ]);
    }

    public function testSingleEntityManagerDefaultTableOptions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_single_em_default_table_options', ['YamlBundle', 'AnnotationsBundle', 'XmlBundle', 'AttributesBundle']);

        $param = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertArrayHasKey('defaultTableOptions', $param);

        $defaults = $param['defaultTableOptions'];

        $this->assertArrayHasKey('charset', $defaults);
        $this->assertArrayHasKey('collation', $defaults);
        $this->assertArrayHasKey('engine', $defaults);

        $this->assertEquals('utf8mb4', $defaults['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $defaults['collation']);
        $this->assertEquals('InnoDB', $defaults['engine']);
    }

    public function testSetTypes(): void
    {
        $container = $this->loadContainer('dbal_types');

        $this->assertEquals(
            ['test' => ['class' => TestType::class]],
            $container->getParameter('doctrine.dbal.connection_factory.types'),
        );
        $this->assertEquals('%doctrine.dbal.connection_factory.types%', $container->getDefinition('doctrine.dbal.connection_factory')->getArgument(0));
    }

    public function testSetCustomFunctions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_functions');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', ['test_string', TestStringFunction::class]);
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomNumericFunction', ['test_numeric', TestNumericFunction::class]);
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomDatetimeFunction', ['test_datetime', TestDatetimeFunction::class]);
    }

    public function testSetNamingStrategy(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_namingstrategy');

        $def1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $def2 = $container->getDefinition('doctrine.orm.em2_configuration');

        $this->assertDICDefinitionMethodCallOnce($def1, 'setNamingStrategy', [0 => new Reference('doctrine.orm.naming_strategy.default')]);
        $this->assertDICDefinitionMethodCallOnce($def2, 'setNamingStrategy', [0 => new Reference('doctrine.orm.naming_strategy.underscore')]);
    }

    public function testSetQuoteStrategy(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_quotestrategy');

        $def1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $def2 = $container->getDefinition('doctrine.orm.em2_configuration');

        $this->assertDICDefinitionMethodCallOnce($def1, 'setQuoteStrategy', [0 => new Reference('doctrine.orm.quote_strategy.default')]);
        $this->assertDICDefinitionMethodCallOnce($def2, 'setQuoteStrategy', [0 => new Reference('doctrine.orm.quote_strategy.ansi')]);
    }

    /**
     * @dataProvider cacheConfigProvider
     * @group legacy
     */
    public function testCacheConfig(?string $expectedClass, string $entityManagerName, ?string $cacheGetter): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_caches');

        $entityManagerId = sprintf('doctrine.orm.%s_entity_manager', $entityManagerName);

        $em = $container->get($entityManagerId);
        assert($em instanceof EntityManager);

        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        if ($cacheGetter === null) {
            return;
        }

        $configuration = $em->getConfiguration();
        $cache         = $configuration->$cacheGetter();

        if ($expectedClass === null) {
            $this->assertNull($cache);
        } else {
            $this->assertInstanceOf($expectedClass, $cache);
        }
    }

    public static function cacheConfigProvider(): Generator
    {
        yield 'metadata_cache_none' => [
            'expectedClass' => PhpArrayAdapter::class,
            'entityManagerName' => 'metadata_cache_none',
            'cacheGetter' => 'getMetadataCache',
        ];

        yield 'metadata_cache_pool' => [
            'expectedClass' => ArrayAdapter::class,
            'entityManagerName' => 'metadata_cache_pool',
            'cacheGetter' => 'getMetadataCache',
        ];

        yield 'metadata_cache_service_psr6' => [
            'expectedClass' => ArrayAdapter::class,
            'entityManagerName' => 'metadata_cache_service_psr6',
            'cacheGetter' => 'getMetadataCache',
        ];

        yield 'metadata_cache_service_doctrine' => [
            'expectedClass' => ArrayAdapter::class,
            'entityManagerName' => 'metadata_cache_service_doctrine',
            'cacheGetter' => 'getMetadataCache',
        ];

        if (method_exists(OrmConfiguration::class, 'getQueryCacheImpl')) {
            yield 'query_cache_pool' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'query_cache_pool',
                'cacheGetter' => 'getQueryCacheImpl',
            ];

            yield 'query_cache_service_psr6' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'query_cache_service_psr6',
                'cacheGetter' => 'getQueryCacheImpl',
            ];

            yield 'query_cache_service_doctrine' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'query_cache_service_doctrine',
                'cacheGetter' => 'getQueryCacheImpl',
            ];

            yield 'result_cache_pool' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'result_cache_pool',
                'cacheGetter' => 'getResultCacheImpl',
            ];

            yield 'result_cache_service_psr6' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'result_cache_service_psr6',
                'cacheGetter' => 'getResultCacheImpl',
            ];

            yield 'result_cache_service_doctrine' => [
                'expectedClass' => DoctrineProvider::class,
                'entityManagerName' => 'result_cache_service_doctrine',
                'cacheGetter' => 'getResultCacheImpl',
            ];
        }

        yield 'second_level_cache_pool' => [
            'expectedClass' => null,
            'entityManagerName' => 'second_level_cache_pool',
            'cacheGetter' => null,
        ];

        yield 'second_level_cache_service_psr6' => [
            'expectedClass' => null,
            'entityManagerName' => 'second_level_cache_service_psr6',
            'cacheGetter' => null,
        ];

        yield 'second_level_cache_service_doctrine' => [
            'expectedClass' => null,
            'entityManagerName' => 'second_level_cache_service_doctrine',
            'cacheGetter' => null,
        ];
    }

    public function testSecondLevelCache(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_second_level_cache');

        $this->assertTrue($container->has('doctrine.orm.default_configuration'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.cache_configuration'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.region_cache_driver'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.regions_configuration'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.default_cache_factory'));

        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.logger_chain'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.logger_statistics'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.logger.my_service_logger1'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.logger.my_service_logger2'));

        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.region.my_entity_region'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.region.my_service_region'));
        $this->assertTrue($container->has('doctrine.orm.default_second_level_cache.region.my_query_region_filelock'));

        $slcFactoryDef       = $container->getDefinition('doctrine.orm.default_second_level_cache.default_cache_factory');
        $slcRegionsConfDef   = $container->getDefinition('doctrine.orm.default_second_level_cache.regions_configuration');
        $myEntityRegionDef   = $container->getDefinition('doctrine.orm.default_second_level_cache.region.my_entity_region');
        $loggerChainDef      = $container->getDefinition('doctrine.orm.default_second_level_cache.logger_chain');
        $loggerStatisticsDef = $container->getDefinition('doctrine.orm.default_second_level_cache.logger_statistics');
        $myQueryRegionDef    = $container->getDefinition('doctrine.orm.default_second_level_cache.region.my_query_region_filelock');
        $cacheDriverDef      = $container->getDefinition((string) $container->getAlias('doctrine.orm.default_second_level_cache.region_cache_driver'));
        $configDef           = $container->getDefinition('doctrine.orm.default_configuration');
        $slcRegionsConfArgs  = $slcRegionsConfDef->getArguments();
        $myEntityRegionArgs  = $myEntityRegionDef->getArguments();
        $myQueryRegionArgs   = $myQueryRegionDef->getArguments();
        $slcFactoryArgs      = $slcFactoryDef->getArguments();

        $this->assertDICDefinitionClass($slcFactoryDef, '%doctrine.orm.second_level_cache.default_cache_factory.class%');
        $this->assertDICDefinitionClass($slcRegionsConfDef, '%doctrine.orm.second_level_cache.regions_configuration.class%');
        $this->assertDICDefinitionClass($myQueryRegionDef, '%doctrine.orm.second_level_cache.filelock_region.class%');
        $this->assertDICDefinitionClass($myEntityRegionDef, '%doctrine.orm.second_level_cache.default_region.class%');
        $this->assertDICDefinitionClass($loggerChainDef, '%doctrine.orm.second_level_cache.logger_chain.class%');
        $this->assertDICDefinitionClass($loggerStatisticsDef, '%doctrine.orm.second_level_cache.logger_statistics.class%');
        $this->assertDICDefinitionClass($cacheDriverDef, ArrayAdapter::class);
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setSecondLevelCacheConfiguration');
        $this->assertDICDefinitionMethodCallCount($slcFactoryDef, 'setRegion', [], 3);
        $this->assertDICDefinitionMethodCallCount($loggerChainDef, 'setLogger', [], 3);

        $this->assertEquals([3600, 60], $slcRegionsConfArgs);

        $this->assertInstanceOf(Reference::class, $slcFactoryArgs[0]);
        $this->assertInstanceOf(Reference::class, $slcFactoryArgs[1]);

        $this->assertInstanceOf(Reference::class, $myEntityRegionArgs[1]);
        $this->assertInstanceOf(Reference::class, $myQueryRegionArgs[0]);

        $this->assertEquals('my_entity_region', $myEntityRegionArgs[0]);
        $this->assertEquals('doctrine.orm.default_second_level_cache.region.my_entity_region_driver', $myEntityRegionArgs[1]);
        $this->assertEquals(600, $myEntityRegionArgs[2]);

        $this->assertEquals('doctrine.orm.default_second_level_cache.region.my_query_region', $myQueryRegionArgs[0]);
        $this->assertStringContainsString(
            '/doctrine/orm/slc/filelock',
            $myQueryRegionArgs[1],
        );
        $this->assertEquals(60, $myQueryRegionArgs[2]);

        $this->assertEquals('doctrine.orm.default_second_level_cache.regions_configuration', $slcFactoryArgs[0]);
        $this->assertEquals('doctrine.orm.default_second_level_cache.region_cache_driver', $slcFactoryArgs[1]);
    }

    public function testSingleEMSetCustomFunctions(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_single_em_dql_functions');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', ['test_string', TestStringFunction::class]);
    }

    public function testAddCustomHydrationMode(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_hydration_mode');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        /** @psalm-suppress UndefinedClass */
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomHydrationMode', ['test_hydrator', TestHydrator::class]);
    }

    /** @requires PHP 8.1 */
    public function testAddFilter(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_filters');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $args       = [
            ['soft_delete', TestFilter::class],
            ['myFilter', TestFilter::class],
        ];
        $this->assertDICDefinitionMethodCallCount($definition, 'addFilter', $args, 2);

        $definition = $container->getDefinition('doctrine.orm.default_manager_configurator');
        $this->assertDICConstructorArguments($definition, [['soft_delete', 'myFilter'], ['myFilter' => ['myParameter' => 'myValue', 'mySecondParameter' => 'mySecondValue']]]);

        $entityManager = $container->get('doctrine.orm.entity_manager');
        assert($entityManager instanceof EntityManagerInterface);
        $this->assertCount(2, $entityManager->getFilters()->getEnabledFilters());
    }

    public function testDisablingLazyGhostOnOrm3Throws(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        if (method_exists(ProxyFactory::class, 'resetUninitializedProxy')) {
            self::markTestSkipped('This test requires ORM 3.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Lazy ghost objects cannot be disabled for ORM 3.');
        $this->loadContainer('orm_no_lazy_ghost');
    }

    public function testDisablingReportFieldsWhereDeclaredOnOrm3Throws(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        if (class_exists(AnnotationDriver::class)) {
            self::markTestSkipped('This test requires ORM 3.');
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "doctrine.orm.entity_managers.default.report_fields_where_declared": The setting "report_fields_where_declared" cannot be disabled for ORM 3.');
        $this->loadContainer('orm_no_report_fields');
    }

    public function testResolveTargetEntity(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_resolve_target_entity');

        $definition = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addResolveTargetEntity', [UserInterface::class, 'MyUserClass', []]);

        $tags = $definition->getTags();
        unset($tags['container.no_preload']);
        $this->assertEquals(['doctrine.event_listener' => [['event' => 'loadClassMetadata'], ['event' => 'onClassMetadataNotFound']]], $tags);
    }

    public function testSchemaIgnoreClasses(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_schema_ignore_classes');

        $def1 = $container->getDefinition('doctrine.orm.em1_configuration');

        $this->assertDICDefinitionMethodCallOnce($def1, 'setSchemaIgnoreClasses', [0 => ['Class\A', 'Class\B']]);
    }

    public function testAttachEntityListeners(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_attach_entity_listener');

        $definition  = $container->getDefinition('doctrine.orm.default_listeners.attach_entity_listeners');
        $methodCalls = $definition->getMethodCalls();

        $this->assertDICDefinitionMethodCallCount($definition, 'addEntityListener', [], 6);
        $tags = $definition->getTags();
        unset($tags['container.no_preload']);
        $this->assertEquals(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $tags);

        $this->assertEquals($methodCalls[0], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\FooEntity',
                'MyBundles\Listeners\FooEntityListener',
                'prePersist',
                null,
            ],
        ]);

        $this->assertEquals($methodCalls[1], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\FooEntity',
                'MyBundles\Listeners\FooEntityListener',
                'postPersist',
                'postPersist',
            ],
        ]);

        $this->assertEquals($methodCalls[2], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\FooEntity',
                'MyBundles\Listeners\FooEntityListener',
                'postLoad',
                'postLoadHandler',
            ],
        ]);

        $this->assertEquals($methodCalls[3], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\BarEntity',
                'MyBundles\Listeners\BarEntityListener',
                'prePersist',
                'prePersist',
            ],
        ]);

        $this->assertEquals($methodCalls[4], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\BarEntity',
                'MyBundles\Listeners\BarEntityListener',
                'prePersist',
                'prePersistHandler',
            ],
        ]);

        $this->assertEquals($methodCalls[5], [
            'addEntityListener',
            [
                'ExternalBundles\Entities\BarEntity',
                'MyBundles\Listeners\LogDeleteEntityListener',
                'postDelete',
                'postDelete',
            ],
        ]);
    }

    public function testDbalAutoCommit(): void
    {
        $container = $this->loadContainer('dbal_auto_commit');

        $definition = $container->getDefinition('doctrine.dbal.default_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setAutoCommit', [false]);
    }

    public function testDbalOracleConnectstring(): void
    {
        $container = $this->loadContainer('dbal_oracle_connectstring');

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);
        $this->assertSame('scott@sales-server:1521/sales.us.example.com', $config['connectstring']);
    }

    public function testDbalOracleInstancename(): void
    {
        $container = $this->loadContainer('dbal_oracle_instancename');

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);
        $this->assertSame('mySuperInstance', $config['instancename']);
    }

    public function testDbalSchemaFilterNewConfig(): void
    {
        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new WellKnownSchemaFilterPass());
        $container->addCompilerPass(new DbalSchemaFilterPass());

        // ignore table1 table on "default" connection
        $container->register('dummy_filter1', DummySchemaAssetsFilter::class)
            ->setArguments(['table1'])
            ->addTag('doctrine.dbal.schema_filter');

        // ignore table2 table on "connection2" connection
        $container->register('dummy_filter2', DummySchemaAssetsFilter::class)
            ->setArguments(['table2'])
            ->addTag('doctrine.dbal.schema_filter', ['connection' => 'connection2']);

        $this->loadFromFile($container, 'dbal_schema_filter');

        $assetNames               = ['table1', 'table2', 'table3', 't_ignored'];
        $expectedConnectionAssets = [
            // ignores table1 + schema_filter applies
            'connection1' => ['table2', 'table3'],
            // ignores table2, no schema_filter applies
            'connection2' => ['table1', 'table3', 't_ignored'],
            // connection3 has no ignores, handled separately
        ];

        $this->compileContainer($container);

        $getConfiguration = static function (string $connectionName) use ($container): Configuration {
            return $container->get(sprintf('doctrine.dbal.%s_connection', $connectionName))->getConfiguration();
        };

        foreach ($expectedConnectionAssets as $connectionName => $expectedTables) {
            $connConfig = $getConfiguration($connectionName);
            $this->assertSame($expectedTables, array_values(array_filter($assetNames, $connConfig->getSchemaAssetsFilter())), sprintf('Filtering for connection "%s"', $connectionName));
        }
    }

    /** @group legacy */
    public function testWellKnownSchemaFilterDefaultTables(): void
    {
        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new WellKnownSchemaFilterPass());
        $container->addCompilerPass(new DbalSchemaFilterPass());

        $this->loadFromFile($container, 'well_known_schema_filter_default_tables_session');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.well_known_schema_asset_filter');

        $filter = $container->get('well_known_filter');

        $this->assertInstanceOf(BlacklistSchemaAssetFilter::class, $filter);

        if (method_exists(PdoSessionHandler::class, 'configureSchema')) {
            $this->assertNotSame([['sessions']], $definition->getArguments());
            $this->assertTrue($filter->__invoke('sessions'));
        } else {
            $this->assertSame([['sessions']], $definition->getArguments());

            $this->assertSame([['connection' => 'connection1'], ['connection' => 'connection2'], ['connection' => 'connection3']], $definition->getTag('doctrine.dbal.schema_filter'));

            $definition = $container->getDefinition('doctrine.dbal.connection1_schema_asset_filter_manager');

            $this->assertEquals([new Reference('doctrine.dbal.well_known_schema_asset_filter'), new Reference('doctrine.dbal.connection1_regex_schema_filter')], $definition->getArgument(0));
            $this->assertFalse($filter->__invoke('sessions'));
        }

        $this->assertTrue($filter->__invoke('anything_else'));
    }

    /** @group legacy */
    public function testWellKnownSchemaFilterOverriddenTables(): void
    {
        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new WellKnownSchemaFilterPass());
        $container->addCompilerPass(new DbalSchemaFilterPass());

        $this->loadFromFile($container, 'well_known_schema_filter_overridden_tables_session');

        $this->compileContainer($container);

        $filter = $container->get('well_known_filter');

        $this->assertInstanceOf(BlacklistSchemaAssetFilter::class, $filter);

        if (method_exists(PdoSessionHandler::class, 'configureSchema')) {
            $this->assertTrue($filter->__invoke('app_session'));
        } else {
            $this->assertFalse($filter->__invoke('app_session'));
        }
    }

    public function testEntityListenerResolver(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_entity_listener_resolver', ['YamlBundle'], new EntityListenerPass());

        $definition = $container->getDefinition('doctrine.orm.em1_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityListenerResolver', ['doctrine.orm.em1_entity_listener_resolver']);

        $definition = $container->getDefinition('doctrine.orm.em2_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityListenerResolver', ['doctrine.orm.em2_entity_listener_resolver']);

        $listener = $container->getDefinition('doctrine.orm.em1_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'registerService', ['EntityListener', 'entity_listener1']);

        $listener = $container->getDefinition('entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'register', ['entity_listener2']);
    }

    public function testAttachEntityListenerTag(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_attach_entity_listener_tag');

        $this->compileContainer($container);

        $listener = $container->getDefinition('doctrine.orm.em1_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallCount($listener, 'registerService', [
            ['ParentEntityListener', 'children_entity_listener'],
            ['EntityListener1', 'entity_listener1'],
            [InvokableEntityListener::class, 'invokable_entity_listener'],
            [InvokableEntityListener::class, 'invokable_entity_listener'],
        ], 4);

        $listener = $container->getDefinition('doctrine.orm.em2_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'registerService', ['EntityListener2', 'entity_listener2']);

        $attachListener = $container->getDefinition('doctrine.orm.em1_listeners.attach_entity_listeners');
        $this->assertDICDefinitionMethodCallAt(1, $attachListener, 'addEntityListener', ['My/Entity1', 'EntityListener1', 'postLoad']);
        $this->assertDICDefinitionMethodCallAt(2, $attachListener, 'addEntityListener', ['My/Entity1', InvokableEntityListener::class, 'loadClassMetadata', '__invoke']);
        $this->assertDICDefinitionMethodCallAt(3, $attachListener, 'addEntityListener', ['My/Entity1', InvokableEntityListener::class, 'postPersist']);
        $this->assertDICDefinitionMethodCallAt(0, $attachListener, 'addEntityListener', ['My/Entity3', 'ParentEntityListener', 'postLoad']);

        $attachListener = $container->getDefinition('doctrine.orm.em2_listeners.attach_entity_listeners');
        $this->assertDICDefinitionMethodCallOnce($attachListener, 'addEntityListener', ['My/Entity2', 'EntityListener2', 'preFlush', 'preFlushHandler']);
    }

    public function testAttachEntityListenersTwoConnections(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer(['YamlBundle']);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine'));

        $this->loadFromFile($container, 'orm_attach_entity_listeners_two_connections');

        $this->compileContainer($container);

        $defaultEventManager = $container->getDefinition('doctrine.dbal.default_connection.event_manager');
        $this->assertEmpty($defaultEventManager->getMethodCalls());
        $defaultEventManagerArguments = $defaultEventManager->getArguments();

        $this->assertSame([['loadClassMetadata'], 'doctrine.orm.em1_listeners.attach_entity_listeners'], end($defaultEventManagerArguments[1]));

        $foobarEventManager = $container->getDefinition('doctrine.dbal.foobar_connection.event_manager');
        $this->assertEmpty($foobarEventManager->getMethodCalls());
        $foobarEventManagerArguments = $foobarEventManager->getArguments();
        $this->assertSame([['loadClassMetadata'], 'doctrine.orm.em2_listeners.attach_entity_listeners'], end($foobarEventManagerArguments[1]));
    }

    public function testAttachLazyEntityListener(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_attach_lazy_entity_listener');

        $this->compileContainer($container);

        $resolver1 = $container->getDefinition('doctrine.orm.em1_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallAt(0, $resolver1, 'registerService', ['EntityListener1', 'entity_listener1']);
        $this->assertDICDefinitionMethodCallAt(1, $resolver1, 'register', ['entity_listener3']);
        $this->assertDICDefinitionMethodCallAt(2, $resolver1, 'registerService', ['EntityListener4', 'entity_listener4']);

        $serviceLocatorReference = $resolver1->getArgument(0);
        $this->assertInstanceOf(Reference::class, $serviceLocatorReference);
        $serviceLocatorDefinition = $container->getDefinition((string) $serviceLocatorReference);
        $this->assertSame(ServiceLocator::class, $serviceLocatorDefinition->getClass());
        $serviceLocatorMap = $serviceLocatorDefinition->getArgument(0);
        $this->assertSame(['entity_listener1', 'entity_listener4'], array_keys($serviceLocatorMap));

        $resolver2 = $container->findDefinition('custom_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($resolver2, 'registerService', ['EntityListener2', 'entity_listener2']);
    }

    public function testAttachLazyEntityListenerForCustomResolver(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_entity_listener_custom_resolver');

        $this->compileContainer($container);

        $resolver = $container->getDefinition('custom_entity_listener_resolver');
        $this->assertTrue($resolver->isPublic());
        $this->assertEmpty($resolver->getArguments(), 'We must not change the arguments for custom services.');
        $this->assertDICDefinitionMethodCallOnce($resolver, 'registerService', ['EntityListener', 'entity_listener']);
        $this->assertTrue($container->getDefinition('entity_listener')->isPublic());
    }

    public function testLazyEntityListenerResolverWithoutCorrectInterface(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_entity_listener_lazy_resolver_without_interface');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EntityListenerServiceResolver');
        $this->compileContainer($container);
    }

    public function testPrivateLazyEntityListener(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_entity_listener_lazy_private');

        $this->compileContainer($container);

        $this->assertTrue($container->getDefinition('doctrine.orm.em1_entity_listener_resolver')->isPublic());
    }

    public function testAbstractEntityListener(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([]);
        $loader    = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_entity_listener_abstract');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The service ".*" must not be abstract\./');

        $this->compileContainer($container);
    }

    public function testRepositoryFactory(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->loadContainer('orm_repository_factory');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setRepositoryFactory', ['repository_factory']);
    }

    public function testDisableSchemaValidation(): void
    {
        $container           = $this->loadContainer('dbal_collect_schema_errors_enable');
        $collectorDefinition = $container->getDefinition('data_collector.doctrine');
        $this->assertTrue($collectorDefinition->getArguments()[1]);

        $container           = $this->loadContainer('dbal_collect_schema_errors_disable');
        $collectorDefinition = $container->getDefinition('data_collector.doctrine');
        $this->assertFalse($collectorDefinition->getArguments()[1]);

        $container           = $this->loadContainer('dbal_collect_schema_errors_disable_no_profiling');
        $collectorDefinition = $container->getDefinition('data_collector.doctrine');
        $this->assertFalse($collectorDefinition->getArguments()[1]);
    }

    /** @param list<string> $bundles */
    private function loadContainer(
        string $fixture,
        array $bundles = ['YamlBundle'],
        ?CompilerPassInterface $compilerPass = null
    ): ContainerBuilder {
        $container = $this->getContainer($bundles);
        $container->registerExtension(new DoctrineExtension());

        $this->loadFromFile($container, $fixture);

        if ($compilerPass !== null) {
            $container->addCompilerPass($compilerPass);
        }

        $this->compileContainer($container);

        return $container;
    }

    /** @param list<string> $bundles */
    private function getContainer(array $bundles): ContainerBuilder
    {
        $map         = [];
        $metadataMap = [];
        foreach ($bundles as $bundle) {
            $bundleDir       = __DIR__ . '/Fixtures/Bundles/' . $bundle;
            $bundleNamespace = 'Fixtures\\Bundles\\' . $bundle;

            if (is_dir($bundleDir . '/src')) {
                require_once $bundleDir . '/src/' . $bundle . '.php';
            } else {
                require_once $bundleDir . '/' . $bundle . '.php';
            }

            $map[$bundle] = $bundleNamespace . '\\' . $bundle;

            $metadataMap[$bundle] = [
                'path' => $bundleDir,
                'namespace' => $bundleNamespace,
            ];
        }

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => $map,
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__ . '/../../', // src dir
            'kernel.project_dir' => __DIR__ . '/../../', // src dir
            'kernel.bundles_metadata' => $metadataMap,
            'container.build_id' => uniqid(),
        ]));

        // Register dummy cache services so we don't have to load the FrameworkExtension
        $container->setDefinition('cache.system', (new Definition(ArrayAdapter::class))->setPublic(true));
        $container->setDefinition('cache.app', (new Definition(ArrayAdapter::class))->setPublic(true));

        return $container;
    }

    /**
     * Assertion on the Class of a DIC Service Definition.
     */
    private function assertDICDefinitionClass(Definition $definition, string $expectedClass): void
    {
        $this->assertEquals($expectedClass, $definition->getClass(), 'Expected Class of the DIC Container Service Definition is wrong.');
    }

    /** @param list<mixed> $args */
    private function assertDICConstructorArguments(Definition $definition, array $args): void
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '" . $definition->getClass() . "' don't match.");
    }

    /** @param list<mixed> $params */
    private function assertDICDefinitionMethodCallAt(
        int $pos,
        Definition $definition,
        string $methodName,
        ?array $params = null
    ): void {
        $calls = $definition->getMethodCalls();
        if (! isset($calls[$pos][0])) {
            $this->fail(sprintf('Method call at position %s not found!', $pos));
        }

        $this->assertEquals($methodName, $calls[$pos][0], "Method '" . $methodName . "' is expected to be called at position " . $pos . '.');

        if ($params === null) {
            return;
        }

        $this->assertEquals($params, $calls[$pos][1], "Expected parameters to methods '" . $methodName . "' do not match the actual parameters.");
    }

    /**
     * Assertion for the DI Container, check if the given definition contains a method call with the given parameters.
     *
     * @param list<mixed> $params
     */
    private function assertDICDefinitionMethodCallOnce(
        Definition $definition,
        string $methodName,
        ?array $params = null
    ): void {
        $calls  = $definition->getMethodCalls();
        $called = false;
        foreach ($calls as $call) {
            if ($call[0] !== $methodName) {
                continue;
            }

            if ($called) {
                $this->fail("Method '" . $methodName . "' is expected to be called only once, a second call was registered though.");
            } else {
                $called = true;
                if ($params !== null) {
                    $this->assertEquals($params, $call[1], "Expected parameters to methods '" . $methodName . "' do not match the actual parameters.");
                }
            }
        }

        if ($called) {
            return;
        }

        $this->fail("Method '" . $methodName . "' is expected to be called once, definition does not contain a call though.");
    }

    /** @param list<list<string>> $params */
    private function assertDICDefinitionMethodCallCount(
        Definition $definition,
        string $methodName,
        array $params = [],
        int $nbCalls = 1
    ): void {
        $calls  = $definition->getMethodCalls();
        $called = 0;
        foreach ($calls as $call) {
            if ($call[0] !== $methodName) {
                continue;
            }

            if ($called > $nbCalls) {
                break;
            }

            if (isset($params[$called])) {
                $this->assertEquals($params[$called], $call[1], "Expected parameters to methods '" . $methodName . "' do not match the actual parameters.");
            }

            $called++;
        }

        $this->assertEquals($nbCalls, $called, sprintf('The method "%s" should be called %d times', $methodName, $nbCalls));
    }

    private function compileContainer(ContainerBuilder $container): void
    {
        $passConfig = $container->getCompilerPassConfig();
        $passConfig->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $passConfig->setRemovingPasses([]);
        $passConfig->addPass(new CacheCompatibilityPass());
        $container->compile();
    }
}

class DummySchemaAssetsFilter
{
    private string $tableToIgnore;

    public function __construct(string $tableToIgnore)
    {
        $this->tableToIgnore = $tableToIgnore;
    }

    public function __invoke(string $assetName): bool
    {
        return $assetName !== $this->tableToIgnore;
    }
}
