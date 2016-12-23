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

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\ORM\Version;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionTemplatesPass;

abstract class AbstractDoctrineExtensionTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDbalLoadFromXmlMultipleConnections()
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
        $this->assertSame('pgsql_db', $config['dbname']);
        $this->assertSame('pgsql_user', $config['user']);
        $this->assertSame('pgsql_s3cr3t', $config['password']);
        $this->assertSame('require', $config['sslmode']);
        $this->assertSame('postgresql-ca.pem', $config['sslrootcert']);
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

    public function testDbalLoadFromXmlSingleConnections()
    {
        $container = $this->loadContainer('dbal_service_single_connection');

        // doctrine.dbal.mysql_connection
        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
        $this->assertEquals('5.6.20', $config['serverVersion']);
    }

    public function testDbalLoadSingleMasterSlaveConnection()
    {
        $container = $this->loadContainer('dbal_service_single_master_slave_connection');

        // doctrine.dbal.mysql_connection
        $param = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('Doctrine\\DBAL\\Connections\\MasterSlaveConnection', $param['wrapperClass']);
        $this->assertTrue($param['keepSlave']);
        $this->assertEquals(
            array('user' => 'mysql_user', 'password' => 'mysql_s3cr3t',
                  'port' => null, 'dbname' => 'mysql_db', 'host' => 'localhost',
                  'unix_socket' => '/path/to/mysqld.sock',
                  'defaultTableOptions' => array(),
            ),
            $param['master']
        );
        $this->assertEquals(
            array(
                'user' => 'slave_user', 'password' => 'slave_s3cr3t', 'port' => null, 'dbname' => 'slave_db',
                'host' => 'localhost', 'unix_socket' => '/path/to/mysqld_slave.sock',
            ),
            $param['slaves']['slave1']
        );
    }

    public function testDbalLoadPoolShardingConnection()
    {
        $container = $this->loadContainer('dbal_service_pool_sharding_connection');

        // doctrine.dbal.mysql_connection
        $param = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('Doctrine\\DBAL\\Sharding\\PoolingShardConnection', $param['wrapperClass']);
        $this->assertEquals(new Reference('foo.shard_choser'), $param['shardChoser']);
        $this->assertEquals(
            array('user' => 'mysql_user', 'password' => 'mysql_s3cr3t',
                  'port' => null, 'dbname' => 'mysql_db', 'host' => 'localhost',
                  'unix_socket' => '/path/to/mysqld.sock',
                  'defaultTableOptions' => array(),
            ),
            $param['global']
        );
        $this->assertEquals(
            array(
                'user' => 'shard_user', 'password' => 'shard_s3cr3t', 'port' => null, 'dbname' => 'shard_db',
                'host' => 'localhost', 'unix_socket' => '/path/to/mysqld_shard.sock', 'id' => 1,
            ),
            $param['shards'][0]
        );
    }

    public function testDbalLoadSavepointsForNestedTransactions()
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

    public function testLoadSimpleSingleConnection()
    {
        $container = $this->loadContainer('orm_service_simple_single_entity_manager');

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, array(
            array(
                'dbname' => 'db',
                'host' => 'localhost',
                'port' => null,
                'user' => 'root',
                'password' => null,
                'driver' => 'pdo_mysql',
                'driverOptions' => array(),
                'defaultTableOptions' => array(),
            ),
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
            array(),
        ));

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        if (method_exists($definition, 'getFactory')) {
            $this->assertEquals(array('%doctrine.orm.entity_manager.class%', 'create'), $definition->getFactory());
        } else {
            $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
            $this->assertEquals('create', $definition->getFactoryMethod());
        }

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration'),
        ));
    }

    /**
     * The PDO driver doesn't require a database name to be to set when connecting to a database server
     */
    public function testLoadSimpleSingleConnectionWithoutDbName()
    {

        $container = $this->loadContainer('orm_service_simple_single_entity_manager_without_dbname');

        /** @var Definition $definition */
        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, array(
                array(
                    'host' => 'localhost',
                    'port' => null,
                    'user' => 'root',
                    'password' => null,
                    'driver' => 'pdo_mysql',
                    'driverOptions' => array(),
                    'defaultTableOptions' => array(),
                ),
                new Reference('doctrine.dbal.default_connection.configuration'),
                new Reference('doctrine.dbal.default_connection.event_manager'),
                array(),
            ));

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        if (method_exists($definition, 'getFactory')) {
            $factory = $definition->getFactory();
        } else {
            $factory[0] = $definition->getFactoryClass();
            $factory[1] = $definition->getFactoryMethod();
        }

        $this->assertEquals('%doctrine.orm.entity_manager.class%', $factory[0]);
        $this->assertEquals('create', $factory[1]);

        $this->assertDICConstructorArguments($definition, array(
                new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration')
            ));
    }

    public function testLoadSingleConnection()
    {
        $container = $this->loadContainer('orm_service_single_entity_manager');

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, array(
            array(
                'host' => 'localhost',
                'driver' => 'pdo_sqlite',
                'driverOptions' => array(),
                'user' => 'sqlite_user',
                'port' => null,
                'password' => 'sqlite_s3cr3t',
                'dbname' => 'sqlite_db',
                'memory' => true,
                'defaultTableOptions' => array(),
            ),
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
            array(),
        ));

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        if (method_exists($definition, 'setFactory')) {
            $this->assertEquals(array('%doctrine.orm.entity_manager.class%', 'create'), $definition->getFactory());
        } else {
            $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
            $this->assertEquals('create', $definition->getFactoryMethod());
        }

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration'),
        ));

        $configDef = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setDefaultRepositoryClassName', array('Acme\Doctrine\Repository'));
    }

    public function testLoadMultipleConnections()
    {
        $container = $this->loadContainer('orm_service_multiple_entity_managers');

        $definition = $container->getDefinition('doctrine.dbal.conn1_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn1_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn1_connection.event_manager', (string) $args[2]);

        $this->assertEquals('doctrine.orm.em2_entity_manager', (string) $container->getAlias('doctrine.orm.entity_manager'));

        $definition = $container->getDefinition('doctrine.orm.em1_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        if (method_exists($definition, 'getFactory')) {
            $this->assertEquals(array('%doctrine.orm.entity_manager.class%', 'create'), $definition->getFactory());
        } else {
            $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
            $this->assertEquals('create', $definition->getFactoryMethod());
        }

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.em1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.dbal.conn2_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn2_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn2_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.em2_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        if (method_exists($definition, 'getFactory')) {
            $this->assertEquals(array('%doctrine.orm.entity_manager.class%', 'create'), $definition->getFactory());
        } else {
            $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
            $this->assertEquals('create', $definition->getFactoryMethod());
        }

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.em2_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.em1_metadata_cache'));
        $this->assertEquals('%doctrine_cache.xcache.class%', $definition->getClass());

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.em1_query_cache'));
        $this->assertEquals('%doctrine_cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.em1_result_cache'));
        $this->assertEquals('%doctrine_cache.array.class%', $definition->getClass());
    }

    public function testLoadLogging()
    {
        $container = $this->loadContainer('dbal_logging');

        $definition = $container->getDefinition('doctrine.dbal.log_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setSQLLogger', array(new Reference('doctrine.dbal.logger')));

        $definition = $container->getDefinition('doctrine.dbal.profile_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setSQLLogger', array(new Reference('doctrine.dbal.logger.profiling.profile')));

        $definition = $container->getDefinition('doctrine.dbal.both_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setSQLLogger', array(new Reference('doctrine.dbal.logger.chain.both')));
    }

    public function testEntityManagerMetadataCacheDriverConfiguration()
    {
        $container = $this->loadContainer('orm_service_multiple_entity_managers');

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.em1_metadata_cache'));
        $this->assertDICDefinitionClass($definition, '%doctrine_cache.xcache.class%');

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.em2_metadata_cache'));
        $this->assertDICDefinitionClass($definition, '%doctrine_cache.apc.class%');
    }

    public function testEntityManagerMemcacheMetadataCacheDriverConfiguration()
    {
        $container = $this->loadContainer('orm_service_simple_single_entity_manager');

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.default_metadata_cache'));
        $this->assertDICDefinitionClass($definition, '%doctrine_cache.memcache.class%');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setMemcache',
            array(new Reference('doctrine_cache.services.doctrine.orm.default_metadata_cache.connection'))
        );

        $definition = $container->getDefinition('doctrine_cache.services.doctrine.orm.default_metadata_cache.connection');
        $this->assertDICDefinitionClass($definition, '%doctrine_cache.memcache.connection.class%');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addServer', array(
            'localhost', '11211',
        ));
    }

    public function testDependencyInjectionImportsOverrideDefaults()
    {
        $container = $this->loadContainer('orm_imports');

        $cacheDefinition = $container->getDefinition($container->getAlias('doctrine.orm.default_metadata_cache'));
        $this->assertEquals('%doctrine_cache.apc.class%', $cacheDefinition->getClass());

        $configDefinition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDefinition, 'setAutoGenerateProxyClasses', array('%doctrine.orm.auto_generate_proxy_classes%'));
    }

    public function testSingleEntityManagerMultipleMappingBundleDefinitions()
    {
        $container = $this->loadContainer('orm_single_em_bundle_mappings', array('YamlBundle', 'AnnotationsBundle', 'XmlBundle'));

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');

        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ));

        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity',
        ));

        $this->assertDICDefinitionMethodCallAt(2, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle',
        ));

        $annDef = $container->getDefinition('doctrine.orm.default_annotation_metadata_driver');
        $this->assertDICConstructorArguments($annDef, array(
            new Reference('doctrine.orm.metadata.annotation_reader'),
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'AnnotationsBundle'.DIRECTORY_SEPARATOR.'Entity'),
        ));

        $ymlDef = $container->getDefinition('doctrine.orm.default_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, array(
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'YamlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine' => 'Fixtures\Bundles\YamlBundle\Entity'),
        ));

        $xmlDef = $container->getDefinition('doctrine.orm.default_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, array(
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'XmlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine' => 'Fixtures\Bundles\XmlBundle'),
        ));
    }

    public function testMultipleEntityManagersMappingBundleDefinitions()
    {
        $container = $this->loadContainer('orm_multiple_em_bundle_mappings', array('YamlBundle', 'AnnotationsBundle', 'XmlBundle'));

        $this->assertEquals(array('em1' => 'doctrine.orm.em1_entity_manager', 'em2' => 'doctrine.orm.em2_entity_manager'), $container->getParameter('doctrine.entity_managers'), "Set of the existing EntityManagers names is incorrect.");
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), "Set of the existing EntityManagers names is incorrect.");

        $def1 = $container->getDefinition('doctrine.orm.em1_metadata_driver');
        $def2 = $container->getDefinition('doctrine.orm.em2_metadata_driver');

        $this->assertDICDefinitionMethodCallAt(0, $def1, 'addDriver', array(
            new Reference('doctrine.orm.em1_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ));

        $this->assertDICDefinitionMethodCallAt(0, $def2, 'addDriver', array(
            new Reference('doctrine.orm.em2_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity',
        ));

        $this->assertDICDefinitionMethodCallAt(1, $def2, 'addDriver', array(
            new Reference('doctrine.orm.em2_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle',
        ));

        $annDef = $container->getDefinition('doctrine.orm.em1_annotation_metadata_driver');
        $this->assertDICConstructorArguments($annDef, array(
            new Reference('doctrine.orm.metadata.annotation_reader'),
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'AnnotationsBundle'.DIRECTORY_SEPARATOR.'Entity'),
        ));

        $ymlDef = $container->getDefinition('doctrine.orm.em2_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, array(
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'YamlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine' => 'Fixtures\Bundles\YamlBundle\Entity'),
        ));

        $xmlDef = $container->getDefinition('doctrine.orm.em2_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, array(
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'XmlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine' => 'Fixtures\Bundles\XmlBundle'),
        ));
    }

    public function testSingleEntityManagerDefaultTableOptions()
    {
        $container = $this->loadContainer('orm_single_em_default_table_options', array('YamlBundle', 'AnnotationsBundle', 'XmlBundle'));

        $param = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertArrayHasKey('defaultTableOptions',$param);

        $defaults = $param['defaultTableOptions'];

        $this->assertArrayHasKey('charset', $defaults);
        $this->assertArrayHasKey('collate', $defaults);
        $this->assertArrayHasKey('engine', $defaults);

        $this->assertEquals('utf8mb4',$defaults['charset']);
        $this->assertEquals('utf8mb4_unicode_ci',$defaults['collate']);
        $this->assertEquals('InnoDB',$defaults['engine']);

    }

    public function testSetTypes()
    {
        $container = $this->loadContainer('dbal_types');

        $this->assertEquals(
            array('test' => array('class' => 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType', 'commented' => true)),
            $container->getParameter('doctrine.dbal.connection_factory.types')
        );
        $this->assertEquals('%doctrine.dbal.connection_factory.types%', $container->getDefinition('doctrine.dbal.connection_factory')->getArgument(0));
    }

    public function testSetCustomFunctions()
    {
        $container = $this->loadContainer('orm_functions');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', array('test_string', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestStringFunction'));
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomNumericFunction', array('test_numeric', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestNumericFunction'));
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomDatetimeFunction', array('test_datetime', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestDatetimeFunction'));
    }

    public function testSetNamingStrategy()
    {
        if (version_compare(Version::VERSION, "2.3.0-DEV") < 0) {
            $this->markTestSkipped('Naming Strategies are not available');
        }
        $container = $this->loadContainer('orm_namingstrategy');

        $def1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $def2 = $container->getDefinition('doctrine.orm.em2_configuration');

        $this->assertDICDefinitionMethodCallOnce($def1, 'setNamingStrategy', array(0 => new Reference('doctrine.orm.naming_strategy.default')));
        $this->assertDICDefinitionMethodCallOnce($def2, 'setNamingStrategy', array(0 => new Reference('doctrine.orm.naming_strategy.underscore')));
    }

    public function testSetQuoteStrategy()
    {
        if (version_compare(Version::VERSION, "2.3.0-DEV") < 0) {
            $this->markTestSkipped('Quote Strategies are not available');
        }
        $container = $this->loadContainer('orm_quotestrategy');

        $def1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $def2 = $container->getDefinition('doctrine.orm.em2_configuration');

        $this->assertDICDefinitionMethodCallOnce($def1, 'setQuoteStrategy', array(0 => new Reference('doctrine.orm.quote_strategy.default')));
        $this->assertDICDefinitionMethodCallOnce($def2, 'setQuoteStrategy', array(0 => new Reference('doctrine.orm.quote_strategy.ansi')));
    }

    public function testSecondLevelCache()
    {
        if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
            $this->markTestSkipped('Second-level cache requires doctrine-orm 2.5.0 or newer');
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

        $slcFactoryDef = $container->getDefinition('doctrine.orm.default_second_level_cache.default_cache_factory');
        $myEntityRegionDef = $container->getDefinition('doctrine.orm.default_second_level_cache.region.my_entity_region');
        $loggerChainDef = $container->getDefinition('doctrine.orm.default_second_level_cache.logger_chain');
        $loggerStatisticsDef = $container->getDefinition('doctrine.orm.default_second_level_cache.logger_statistics');
        $myQueryRegionDef = $container->getDefinition('doctrine.orm.default_second_level_cache.region.my_query_region_filelock');
        $cacheDriverDef = $container->getDefinition($container->getAlias('doctrine.orm.default_second_level_cache.region_cache_driver'));
        $configDef = $container->getDefinition('doctrine.orm.default_configuration');
        $myEntityRegionArgs = $myEntityRegionDef->getArguments();
        $myQueryRegionArgs = $myQueryRegionDef->getArguments();
        $slcFactoryArgs = $slcFactoryDef->getArguments();

        $this->assertDICDefinitionClass($slcFactoryDef, '%doctrine.orm.second_level_cache.default_cache_factory.class%');
        $this->assertDICDefinitionClass($myQueryRegionDef, '%doctrine.orm.second_level_cache.filelock_region.class%');
        $this->assertDICDefinitionClass($myEntityRegionDef, '%doctrine.orm.second_level_cache.default_region.class%');
        $this->assertDICDefinitionClass($loggerChainDef, '%doctrine.orm.second_level_cache.logger_chain.class%');
        $this->assertDICDefinitionClass($loggerStatisticsDef, '%doctrine.orm.second_level_cache.logger_statistics.class%');
        $this->assertDICDefinitionClass($cacheDriverDef, '%doctrine_cache.array.class%');
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setSecondLevelCacheConfiguration');
        $this->assertDICDefinitionMethodCallCount($slcFactoryDef, 'setRegion', array(), 3);
        $this->assertDICDefinitionMethodCallCount($loggerChainDef, 'setLogger', array(), 3);

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $slcFactoryArgs[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $slcFactoryArgs[1]);

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $myEntityRegionArgs[1]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $myQueryRegionArgs[0]);

        $this->assertEquals('my_entity_region', $myEntityRegionArgs[0]);
        $this->assertEquals('doctrine.orm.default_second_level_cache.region.my_entity_region_driver', $myEntityRegionArgs[1]);
        $this->assertEquals(600, $myEntityRegionArgs[2]);

        $this->assertEquals('doctrine.orm.default_second_level_cache.region.my_query_region', $myQueryRegionArgs[0]);
        $this->assertContains('/doctrine/orm/slc/filelock', $myQueryRegionArgs[1]);
        $this->assertEquals(60, $myQueryRegionArgs[2]);

        $this->assertEquals('doctrine.orm.default_second_level_cache.regions_configuration', $slcFactoryArgs[0]);
        $this->assertEquals('doctrine.orm.default_second_level_cache.region_cache_driver', $slcFactoryArgs[1]);
    }

    public function testSingleEMSetCustomFunctions()
    {
        $container = $this->loadContainer('orm_single_em_dql_functions');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', array('test_string', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestStringFunction'));
    }

    public function testAddCustomHydrationMode()
    {
        $container = $this->loadContainer('orm_hydration_mode');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomHydrationMode', array('test_hydrator', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestHydrator'));
    }

    public function testAddFilter()
    {
        $container = $this->loadContainer('orm_filters');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $args = array(
            array('soft_delete', 'Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestFilter'),
            array('myFilter', 'Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestFilter'),
        );
        $this->assertDICDefinitionMethodCallCount($definition, 'addFilter', $args, 2);

        $definition = $container->getDefinition('doctrine.orm.default_manager_configurator');
        $this->assertDICConstructorArguments($definition, array(array('soft_delete', 'myFilter'), array('myFilter' => array('myParameter' => 'myValue', 'mySecondParameter' => 'mySecondValue'))));

        // Let's create the instance to check the configurator work.
        /** @var $entityManager \Doctrine\ORM\EntityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->assertCount(2, $entityManager->getFilters()->getEnabledFilters());
    }

    public function testResolveTargetEntity()
    {
        $container = $this->loadContainer('orm_resolve_target_entity');

        $definition = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addResolveTargetEntity', array('Symfony\Component\Security\Core\User\UserInterface', 'MyUserClass', array()));

        if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
            $this->assertEquals(array('doctrine.event_listener' => array(array('event' => 'loadClassMetadata'))), $definition->getTags());
        } else {
            $this->assertEquals(array('doctrine.event_subscriber' => array(array())), $definition->getTags());
        }
    }

    public function testAttachEntityListeners()
    {
        if (version_compare(Version::VERSION, '2.5.0-DEV') < 0 ) {
            $this->markTestSkipped('This test requires ORM 2.5-dev.');
        }

        $container = $this->loadContainer('orm_attach_entity_listener');

        $definition = $container->getDefinition('doctrine.orm.default_listeners.attach_entity_listeners');
        $methodCalls = $definition->getMethodCalls();

        $this->assertDICDefinitionMethodCallCount($definition, 'addEntityListener', array(), 6);
        $this->assertEquals(array('doctrine.event_listener' => array( array('event' => 'loadClassMetadata') ) ), $definition->getTags());

        $this->assertEquals($methodCalls[0], array('addEntityListener', array (
            'ExternalBundles\Entities\FooEntity',
            'MyBundles\Listeners\FooEntityListener',
            'prePersist',
            null,
        )));

        $this->assertEquals($methodCalls[1], array('addEntityListener', array (
            'ExternalBundles\Entities\FooEntity',
            'MyBundles\Listeners\FooEntityListener',
            'postPersist',
            'postPersist',
        )));

        $this->assertEquals($methodCalls[2], array('addEntityListener', array (
            'ExternalBundles\Entities\FooEntity',
            'MyBundles\Listeners\FooEntityListener',
            'postLoad',
            'postLoadHandler',
        )));

        $this->assertEquals($methodCalls[3], array('addEntityListener', array (
            'ExternalBundles\Entities\BarEntity',
            'MyBundles\Listeners\BarEntityListener',
            'prePersist',
            'prePersist',
        )));

        $this->assertEquals($methodCalls[4], array('addEntityListener', array (
            'ExternalBundles\Entities\BarEntity',
            'MyBundles\Listeners\BarEntityListener',
            'prePersist',
            'prePersistHandler',
        )));

        $this->assertEquals($methodCalls[5], array('addEntityListener', array (
            'ExternalBundles\Entities\BarEntity',
            'MyBundles\Listeners\LogDeleteEntityListener',
            'postDelete',
            'postDelete',
        )));
    }

    public function testDbalAutoCommit()
    {
        $container = $this->loadContainer('dbal_auto_commit');

        $definition = $container->getDefinition('doctrine.dbal.default_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setAutoCommit', array(false));
    }

    public function testDbalOracleConnectstring()
    {
        $container = $this->loadContainer('dbal_oracle_connectstring');

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);
        $this->assertSame('scott@sales-server:1521/sales.us.example.com', $config['connectstring']);
    }

    public function testDbalOracleInstancename()
    {
        $container = $this->loadContainer('dbal_oracle_instancename');

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);
        $this->assertSame('mySuperInstance', $config['instancename']);
    }

    public function testDbalSchemaFilter()
    {
        $container = $this->loadContainer('dbal_schema_filter');

        $definition = $container->getDefinition('doctrine.dbal.default_connection.configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setFilterSchemaAssetsExpression', array('^sf2_'));
    }

    public function testEntityListenerResolver()
    {
        $container = $this->loadContainer('orm_entity_listener_resolver', array('YamlBundle'), new EntityListenerPass());

        $definition = $container->getDefinition('doctrine.orm.em1_configuration');
        if (version_compare(Version::VERSION, "2.4.0-DEV") >= 0) {
            $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityListenerResolver', array(new Reference('doctrine.orm.em1_entity_listener_resolver')));
        }

        $definition = $container->getDefinition('doctrine.orm.em2_configuration');
        if (version_compare(Version::VERSION, "2.4.0-DEV") >= 0) {
            $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityListenerResolver', array(new Reference('doctrine.orm.em2_entity_listener_resolver')));
        }

        $listener = $container->getDefinition('doctrine.orm.em1_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'register', array(new Reference('entity_listener1')));

        $listener = $container->getDefinition('entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'register', array(new Reference('entity_listener2')));
    }

    public function testAttachEntityListenerTag()
    {
        if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
            $this->markTestSkipped('Attaching entity listeners by tag requires doctrine-orm 2.5.0 or newer');
        }

        $container = $this->getContainer(array());
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $container->addCompilerPass(new EntityListenerPass());

        $this->loadFromFile($container, 'orm_attach_entity_listener_tag');

        $this->compileContainer($container);

        $listener = $container->getDefinition('doctrine.orm.em1_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'register', array(new Reference('entity_listener1')));

        $listener = $container->getDefinition('doctrine.orm.em2_entity_listener_resolver');
        $this->assertDICDefinitionMethodCallOnce($listener, 'register', array(new Reference('entity_listener2')));

        $attachListener = $container->getDefinition('doctrine.orm.em1_listeners.attach_entity_listeners');
        $this->assertDICDefinitionMethodCallOnce($attachListener, 'addEntityListener', array('My/Entity1', 'EntityListener1', 'postLoad'));

        $attachListener = $container->getDefinition('doctrine.orm.em2_listeners.attach_entity_listeners');
        $this->assertDICDefinitionMethodCallOnce($attachListener, 'addEntityListener', array('My/Entity2', 'EntityListener2', 'preFlush', 'preFlushHandler'));
    }

    public function testRepositoryFactory()
    {
        $container = $this->loadContainer('orm_repository_factory');

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setRepositoryFactory', array('repository_factory'));
    }

    private function loadContainer($fixture, array $bundles = array('YamlBundle'), CompilerPassInterface $compilerPass = null)
    {
        $container = $this->getContainer($bundles);
        $container->registerExtension(new DoctrineExtension());

        $this->loadFromFile($container, $fixture);

        if (null !== $compilerPass) {
            $container->addCompilerPass($compilerPass);
        }

        $this->compileContainer($container);

        return $container;
    }

    private function getContainer(array $bundles)
    {
        $map = array();
        foreach ($bundles as $bundle) {
            require_once __DIR__.'/Fixtures/Bundles/'.$bundle.'/'.$bundle.'.php';

            $map[$bundle] = 'Fixtures\\Bundles\\'.$bundle.'\\'.$bundle;
        }

        return new ContainerBuilder(new ParameterBag(array(
            'kernel.debug' => false,
            'kernel.bundles' => $map,
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__.'/../../', // src dir
        )));
    }

    /**
     * Assertion on the Class of a DIC Service Definition.
     *
     * @param Definition $definition
     * @param string     $expectedClass
     */
    private function assertDICDefinitionClass(Definition $definition, $expectedClass)
    {
        $this->assertEquals($expectedClass, $definition->getClass(), 'Expected Class of the DIC Container Service Definition is wrong.');
    }

    private function assertDICConstructorArguments(Definition $definition, $args)
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '".$definition->getClass()."' don't match.");
    }

    private function assertDICDefinitionMethodCallAt($pos, Definition $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        if (isset($calls[$pos][0])) {
            $this->assertEquals($methodName, $calls[$pos][0], "Method '".$methodName."' is expected to be called at position $pos.");

            if ($params !== null) {
                $this->assertEquals($params, $calls[$pos][1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
            }
        }
    }

    /**
     * Assertion for the DI Container, check if the given definition contains a method call with the given parameters.
     *
     * @param Definition $definition
     * @param string     $methodName
     * @param array      $params
     */
    private function assertDICDefinitionMethodCallOnce(Definition $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        $called = false;
        foreach ($calls as $call) {
            if ($call[0] == $methodName) {
                if ($called) {
                    $this->fail("Method '".$methodName."' is expected to be called only once, a second call was registered though.");
                } else {
                    $called = true;
                    if ($params !== null) {
                        $this->assertEquals($params, $call[1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
                    }
                }
            }
        }
        if (!$called) {
            $this->fail("Method '".$methodName."' is expected to be called once, definition does not contain a call though.");
        }
    }

    private function assertDICDefinitionMethodCallCount(Definition $definition, $methodName, array $params = array(), $nbCalls = 1)
    {
        $calls = $definition->getMethodCalls();
        $called = 0;
        foreach ($calls as $call) {
            if ($call[0] == $methodName) {
                if ($called > $nbCalls) {
                    break;
                }

                if (isset($params[$called])) {
                    $this->assertEquals($params[$called], $call[1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
                }
                $called++;
            }
        }

        $this->assertEquals($nbCalls, $called, sprintf('The method "%s" should be called %d times', $methodName, $nbCalls));
    }

    private function compileContainer(ContainerBuilder $container)
    {
        $container->getCompilerPassConfig()->setOptimizationPasses(array(new ResolveDefinitionTemplatesPass()));
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();
    }
}
