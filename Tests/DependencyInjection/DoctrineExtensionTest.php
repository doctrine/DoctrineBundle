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

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\ORM\Version;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionTemplatesPass;

class DoctrineExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testDbalOverrideDefaultConnection()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $container->registerExtension($extension);

        $extension->load(array(array(), array('dbal' => array('default_connection' => 'foo')), array()), $container);

        // doctrine.dbal.default_connection
        $this->assertEquals('%doctrine.default_connection%', $container->getDefinition('doctrine')->getArgument(3), '->load() overrides existing configuration options');
        $this->assertEquals('foo', $container->getParameter('doctrine.default_connection'), '->load() overrides existing configuration options');
    }

    public function getAutomappingConfigurations()
    {
        return array(
            array(
                array(
                    'em1' => array(
                        'mappings' => array(
                            'YamlBundle' => null,
                        ),
                    ),
                    'em2' => array(
                        'mappings' => array(
                            'XmlBundle' => null,
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'em1' => array(
                        'auto_mapping' => true,
                    ),
                    'em2' => array(
                        'mappings' => array(
                            'XmlBundle' => null,
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'em1' => array(
                        'auto_mapping' => true,
                        'mappings' => array(
                            'YamlBundle' => null,
                        ),
                    ),
                    'em2' => array(
                        'mappings' => array(
                            'XmlBundle' => null,
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @dataProvider getAutomappingConfigurations
     */
    public function testAutomapping(array $entityManagers)
    {
        $extension = new DoctrineExtension();

        if (!method_exists($extension, 'fixManagersAutoMappings')) {
            $this->markTestSkipped('Auto mapping with multiple managers available with Symfony ~2.6');
        }

        $container = $this->getContainer(array(
            'YamlBundle',
            'XmlBundle',
        ));

        $extension->load(
            array(
                array(
                    'dbal' => array(
                        'default_connection' => 'cn1',
                        'connections' => array(
                            'cn1' => array(),
                            'cn2' => array(),
                        ),
                    ),
                    'orm' => array(
                        'entity_managers' => $entityManagers,
                    ),
                ),
            ), $container);

        $configEm1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $configEm2 = $container->getDefinition('doctrine.orm.em2_configuration');

        $this->assertContains(
            array(
                'setEntityNamespaces',
                array(
                    array(
                        'YamlBundle' => 'Fixtures\Bundles\YamlBundle\Entity',
                    ),
                ),
            ),
            $configEm1->getMethodCalls()
        );

        $this->assertContains(
            array(
                'setEntityNamespaces',
                array(
                    array(
                        'XmlBundle' => 'Fixtures\Bundles\XmlBundle\Entity',
                    ),
                ),
            ),
            $configEm2->getMethodCalls()
        );
    }

    public function testDbalLoad()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo')))), array(), array('dbal' => array('default_connection' => 'foo')), array()), $container);

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }

    public function testDependencyInjectionConfigurationDefaults()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array())))))), $container);

        $this->assertFalse($container->getParameter('doctrine.orm.auto_generate_proxy_classes'));
        $this->assertEquals('Doctrine\ORM\Configuration', $container->getParameter('doctrine.orm.configuration.class'));
        $this->assertEquals('Doctrine\ORM\EntityManager', $container->getParameter('doctrine.orm.entity_manager.class'));
        $this->assertEquals('Proxies', $container->getParameter('doctrine.orm.proxy_namespace'));
        $this->assertEquals('Doctrine\Common\Cache\ArrayCache', $container->getParameter('doctrine.orm.cache.array.class'));
        $this->assertEquals('Doctrine\Common\Cache\ApcCache', $container->getParameter('doctrine.orm.cache.apc.class'));
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $container->getParameter('doctrine.orm.cache.memcache.class'));
        $this->assertEquals('localhost', $container->getParameter('doctrine.orm.cache.memcache_host'));
        $this->assertEquals('11211', $container->getParameter('doctrine.orm.cache.memcache_port'));
        $this->assertEquals('Memcache', $container->getParameter('doctrine.orm.cache.memcache_instance.class'));
        $this->assertEquals('Doctrine\Common\Cache\XcacheCache', $container->getParameter('doctrine.orm.cache.xcache.class'));
        $this->assertEquals('Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain', $container->getParameter('doctrine.orm.metadata.driver_chain.class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\AnnotationDriver', $container->getParameter('doctrine.orm.metadata.annotation.class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver', $container->getParameter('doctrine.orm.metadata.xml.class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver', $container->getParameter('doctrine.orm.metadata.yml.class'));

        // second-level cache
        $this->assertEquals('Doctrine\ORM\Cache\DefaultCacheFactory', $container->getParameter('doctrine.orm.second_level_cache.default_cache_factory.class'));
        $this->assertEquals('Doctrine\ORM\Cache\Region\DefaultRegion', $container->getParameter('doctrine.orm.second_level_cache.default_region.class'));
        $this->assertEquals('Doctrine\ORM\Cache\Region\FileLockRegion', $container->getParameter('doctrine.orm.second_level_cache.filelock_region.class'));
        $this->assertEquals('Doctrine\ORM\Cache\Logging\CacheLoggerChain', $container->getParameter('doctrine.orm.second_level_cache.logger_chain.class'));
        $this->assertEquals('Doctrine\ORM\Cache\Logging\StatisticsCacheLogger', $container->getParameter('doctrine.orm.second_level_cache.logger_statistics.class'));
        $this->assertEquals('Doctrine\ORM\Cache\CacheConfiguration', $container->getParameter('doctrine.orm.second_level_cache.cache_configuration.class'));
        $this->assertEquals('Doctrine\ORM\Cache\RegionsConfiguration', $container->getParameter('doctrine.orm.second_level_cache.regions_configuration.class'));

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => true,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array(
                    'mappings' => array('YamlBundle' => array()),
                    ),
                ),
        );

        $container = $this->getContainer();
        $extension->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => $config)), $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_mysql', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('root', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.default_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertEquals(array('default' => 'doctrine.orm.default_entity_manager'), $container->getParameter('doctrine.entity_managers'), "Set of the existing EntityManagers names is incorrect.");
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), "Set of the existing EntityManagers names is incorrect.");

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $calls = array_values($definition->getMethodCalls());
        $this->assertEquals(array('YamlBundle' => 'Fixtures\Bundles\YamlBundle\Entity'), $calls[0][1][0]);
        $this->assertEquals('doctrine.orm.default_metadata_cache', (string) $calls[1][1][0]);
        $this->assertEquals('doctrine.orm.default_query_cache', (string) $calls[2][1][0]);
        $this->assertEquals('doctrine.orm.default_result_cache', (string) $calls[3][1][0]);

        if (version_compare(Version::VERSION, "2.3.0-DEV") >= 0) {
            $this->assertEquals('doctrine.orm.naming_strategy.default', (string) $calls[10][1][0]);
        }
        if (version_compare(Version::VERSION, "2.4.0-DEV") >= 0) {
            $this->assertEquals('doctrine.orm.default_entity_listener_resolver', (string) $calls[11][1][0]);
        }

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.default_metadata_cache'));
        $this->assertEquals('%doctrine_cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.default_query_cache'));
        $this->assertEquals('%doctrine_cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition($container->getAlias('doctrine.orm.default_result_cache'));
        $this->assertEquals('%doctrine_cache.array.class%', $definition->getClass());
    }

    public function testAutoGenerateProxyClasses()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => 'eval',
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array(
                    'mappings' => array('YamlBundle' => array()),
                ),
            ),
        );

        $extension->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => $config)), $container);

        $this->assertEquals(AbstractProxyFactory::AUTOGENERATE_EVAL, $container->getParameter('doctrine.orm.auto_generate_proxy_classes'));
    }

    public function testSingleEntityManagerConfiguration()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array())))))), $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration'),
        ));
    }

    public function testBundleEntityAliases()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))));
        $extension->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityNamespaces',
            array(array('YamlBundle' => 'Fixtures\Bundles\YamlBundle\Entity'))
        );
    }

    public function testOverwriteEntityAliases()
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array('alias' => 'yml')))));
        $extension->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityNamespaces',
            array(array('yml' => 'Fixtures\Bundles\YamlBundle\Entity'))
        );
    }

    public function testYamlBundleMappingDetection()
    {
        $container = $this->getContainer('YamlBundle');
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))));
        $extension->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity',
        ));
    }

    public function testXmlBundleMappingDetection()
    {
        $container = $this->getContainer('XmlBundle');
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('XmlBundle' => array()))));
        $extension->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity',
        ));
    }

    public function testAnnotationsBundleMappingDetection()
    {
        $container = $this->getContainer('AnnotationsBundle');
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('AnnotationsBundle' => array()))));
        $extension->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ));
    }

    public function testOrmMergeConfigs()
    {
        $container = $this->getContainer(array('XmlBundle', 'AnnotationsBundle'));
        $extension = new DoctrineExtension();

        $config1 = $this->getConnectionConfig();
        $config1['orm'] = array(
            'auto_generate_proxy_classes' => true,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array('mappings' => array('AnnotationsBundle' => array())),
            ),
        );
        $config2 = $this->getConnectionConfig();
        $config2['orm'] = array(
            'auto_generate_proxy_classes' => false,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array('mappings' => array('XmlBundle' => array())),
            ),
        );
        $extension->load(array($config1, $config2), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity',
        ));
        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity',
        ));

        $configDef = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setAutoGenerateProxyClasses');

        $calls = $configDef->getMethodCalls();
        foreach ($calls as $call) {
            if ($call[0] === 'setAutoGenerateProxyClasses') {
                $this->assertFalse($container->getParameterBag()->resolveValue($call[1][0]));
                break;
            }
        }
    }

    public function testAnnotationsBundleMappingDetectionWithVendorNamespace()
    {
        $container = $this->getContainer('AnnotationsBundle', 'Vendor');
        $extension = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('AnnotationsBundle' => array()))));
        $extension->load(array($config), $container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.orm.default_annotation_metadata_driver', (string) $calls[0][1][0]);
        $this->assertEquals('Fixtures\Bundles\Vendor\AnnotationsBundle\Entity', $calls[0][1][1]);
    }

    private function getContainer($bundles = 'YamlBundle', $vendor = null)
    {
        $bundles = (array) $bundles;

        $map = array();
        foreach ($bundles as $bundle) {
            require_once __DIR__.'/Fixtures/Bundles/'.($vendor ? $vendor.'/' : '').$bundle.'/'.$bundle.'.php';

            $map[$bundle] = 'Fixtures\\Bundles\\'.($vendor ? $vendor.'\\' : '').$bundle.'\\'.$bundle;
        }

        return new ContainerBuilder(new ParameterBag(array(
            'kernel.debug' => false,
            'kernel.bundles' => $map,
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__.'/../../', // src dir
        )));
    }

    private function assertDICConstructorArguments(Definition $definition, array $args)
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
     * @param array|null $params
     */
    private function assertDICDefinitionMethodCallOnce(Definition $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        $called = false;
        foreach ($calls as $call) {
            if ($call[0] === $methodName) {
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

    private function compileContainer(ContainerBuilder $container)
    {
        $container->getCompilerPassConfig()->setOptimizationPasses(array(new ResolveDefinitionTemplatesPass()));
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();
    }

    private function getConnectionConfig()
    {
        return array('dbal' => array('connections' => array('default' => array('password' => 'foo'))));
    }
}
