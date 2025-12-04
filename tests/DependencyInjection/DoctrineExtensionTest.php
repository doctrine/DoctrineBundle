<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Tests\Builder\BundleConfigurationBuilder;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\Php8EntityListener;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\Php8EventListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappedSuperclass;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;

use function array_map;
use function array_values;
use function class_exists;
use function in_array;
use function interface_exists;
use function is_dir;
use function method_exists;
use function sprintf;
use function sys_get_temp_dir;

class DoctrineExtensionTest extends TestCase
{
    public function testAutowiringAlias(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();
        $config    = BundleConfigurationBuilder::createBuilderWithBaseValues()->build();

        $extension->load([$config], $container);

        $expectedAliases = [
            Connection::class => 'database_connection',
            EntityManagerInterface::class => 'doctrine.orm.entity_manager',
        ];

        foreach ($expectedAliases as $id => $target) {
            $this->assertTrue($container->hasAlias($id), sprintf('The container should have a `%s` alias for autowiring support.', $id));

            $alias = $container->getAlias($id);
            $this->assertEquals($target, (string) $alias, sprintf('The autowiring for `%s` should use `%s`.', $id, $target));
            $this->assertFalse($alias->isPublic(), sprintf('The autowiring alias for `%s` should be private.', $id));
        }
    }

    public function testConnectionAutowiringAlias(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addConnection([
                'connections' => [
                    'default' => ['password' => 'foo'],
                    'purchase_logs' => ['password' => 'foo'],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $expectedAliases = [
            Connection::class . ' $defaultConnection' => 'doctrine.dbal.default_connection',
            Connection::class . ' $purchaseLogsConnection' => 'doctrine.dbal.purchase_logs_connection',
        ];

        foreach ($expectedAliases as $id => $target) {
            $this->assertTrue($container->hasAlias($id), sprintf('The container should have a `%s` alias for autowiring support.', $id));

            $alias = $container->getAlias($id);
            $this->assertEquals($target, (string) $alias, sprintf('The autowiring for `%s` should use `%s`.', $id, $target));
            $this->assertFalse($alias->isPublic(), sprintf('The autowiring alias for `%s` should be private.', $id));
        }
    }

    public function testEntityManagerAutowiringAlias(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer([
            'NewXmlBundle',
            'XmlBundle',
        ]);
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'entity_managers' => [
                    'default' => [
                        'mappings' => ['XmlBundle' => []],
                    ],
                    'purchase_logs' => [
                        'mappings' => ['NewXmlBundle' => []],
                    ],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $expectedAliases = [
            EntityManagerInterface::class . ' $defaultEntityManager' => 'doctrine.orm.default_entity_manager',
            EntityManagerInterface::class . ' $purchaseLogsEntityManager' => 'doctrine.orm.purchase_logs_entity_manager',
        ];

        foreach ($expectedAliases as $id => $target) {
            $this->assertTrue($container->hasAlias($id), sprintf('The container should have a `%s` alias for autowiring support.', $id));

            $alias = $container->getAlias($id);
            $this->assertEquals($target, (string) $alias, sprintf('The autowiring for `%s` should use `%s`.', $id, $target));
            $this->assertFalse($alias->isPublic(), sprintf('The autowiring alias for `%s` should be private.', $id));
        }
    }

    public function testPublicServicesAndAliases(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();
        $config    = BundleConfigurationBuilder::createBuilderWithBaseValues()->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->getDefinition('doctrine')->isPublic());
        $this->assertTrue($container->getAlias('doctrine.orm.entity_manager')->isPublic());
        $this->assertTrue($container->getAlias('database_connection')->isPublic());
    }

    public function testDbalGenerateDefaultConnectionConfiguration(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $container->registerExtension($extension);

        $extension->load([['dbal' => []]], $container);

        // doctrine.dbal.default_connection
        $this->assertEquals('%doctrine.default_connection%', $container->getDefinition('doctrine')->getArgument(3));
        $this->assertEquals('default', $container->getParameter('doctrine.default_connection'));
        $this->assertEquals('root', $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['user']);
        $this->assertNull($container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['password']);
        $this->assertEquals('localhost', $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['host']);
        $this->assertNull($container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['port']);
        $this->assertEquals('pdo_mysql', $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['driver']);
        $this->assertEquals([], $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0)['driverOptions']);
    }

    public function testDbalOverrideDefaultConnection(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $container->registerExtension($extension);

        $extension->load([[], ['dbal' => ['default_connection' => 'foo']], []], $container);

        // doctrine.dbal.default_connection
        $this->assertEquals('%doctrine.default_connection%', $container->getDefinition('doctrine')->getArgument(3), '->load() overrides existing configuration options');
        $this->assertEquals('foo', $container->getParameter('doctrine.default_connection'), '->load() overrides existing configuration options');
    }

    public function testDbalOverrideDefaultConnectionWithAdditionalConfiguration(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $container->registerExtension($extension);

        $extension->load([['dbal' => ['default_connection' => 'foo']], ['dbal' => ['types' => ['foo' => 'App\\Doctrine\\FooType']]]], $container);

        // doctrine.dbal.default_connection
        $this->assertEquals('%doctrine.default_connection%', $container->getDefinition('doctrine')->getArgument(3), '->load() overrides existing configuration options');
        $this->assertEquals('foo', $container->getParameter('doctrine.default_connection'), '->load() overrides existing configuration options');
    }

    public function testDbalInvalidDriverScheme(): void
    {
        $extension = new DoctrineExtension();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "doctrine.dbal.driver_schemes": Registering a scheme with the name of one of the official drivers is forbidden, as those are defined in DBAL itself. The following schemes are forbidden: pdo-mysql, pgsql');

        $extension->load([['dbal' => ['driver_schemes' => ['pdo-mysql' => 'sqlite3', 'pgsql' => 'pgsql', 'other' => 'mysqli']]]], $this->getContainer());
    }

    public function testOrmRequiresDbal(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $extension = new DoctrineExtension();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Configuring the ORM layer requires to configure the DBAL layer as well.',
        );
        $extension->load([['orm' => ['auto_mapping' => true]]], $this->getContainer());
    }

    /** @return mixed[][][][] */
    public static function getAutomappingConfigurations(): array
    {
        return [
            [
                [
                    'em1' => [
                        'mappings' => ['XmlBundle' => null],
                    ],
                    'em2' => [
                        'mappings' => ['NewXmlBundle' => null],
                    ],
                ],
            ],
            [
                [
                    'em1' => ['auto_mapping' => true],
                    'em2' => [
                        'mappings' => ['NewXmlBundle' => null],
                    ],
                ],
            ],
            [
                [
                    'em1' => [
                        'auto_mapping' => true,
                        'mappings' => ['XmlBundle' => null],
                    ],
                    'em2' => [
                        'mappings' => ['NewXmlBundle' => null],
                    ],
                ],
            ],
        ];
    }

    /** @param mixed[][][][] $entityManagers */
    #[DataProvider('getAutomappingConfigurations')]
    public static function testAutomapping(array $entityManagers): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $extension = new DoctrineExtension();

        $container = self::getContainer([
            'XmlBundle',
            'NewXmlBundle',
        ]);

        $extension->load(
            [
                [
                    'dbal' => [
                        'default_connection' => 'cn1',
                        'connections' => [
                            'cn1' => [],
                            'cn2' => [],
                        ],
                    ],
                    'orm' => ['entity_managers' => $entityManagers],
                ],
            ],
            $container,
        );

        $configEm1 = $container->getDefinition('doctrine.orm.em1_configuration');
        $configEm2 = $container->getDefinition('doctrine.orm.em2_configuration');

        self::assertContains(
            [
                'setEntityNamespaces',
                [
                    ['XmlBundle' => 'Fixtures\Bundles\XmlBundle\Entity'],
                ],
            ],
            $configEm1->getMethodCalls(),
        );

        self::assertContains(
            [
                'setEntityNamespaces',
                [
                    ['NewXmlBundle' => 'Fixtures\Bundles\NewXmlBundle\Entity'],
                ],
            ],
            $configEm2->getMethodCalls(),
        );
    }

    public function testDbalLoad(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load([
            ['dbal' => ['connections' => ['default' => ['password' => 'foo']]]],
            [],
            ['dbal' => ['default_connection' => 'foo']],
            [],
        ], $container);

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }

    public function testDbalWrapperClass(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load(
            [
                [
                    'dbal' => [
                        'connections' => [
                            'default' => ['password' => 'foo', 'wrapper_class' => TestWrapperClass::class],
                            'second' => ['password' => 'boo'],
                        ],
                    ],
                ],
                [],
                ['dbal' => ['default_connection' => 'foo']],
                [],
            ],
            $container,
        );

        $this->assertEquals(TestWrapperClass::class, $container->getDefinition('doctrine.dbal.default_connection')->getClass());
        $this->assertNull($container->getDefinition('doctrine.dbal.second_connection')->getClass());
    }

    public function testDependencyInjectionConfigurationDefaults(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();
        $config    = BundleConfigurationBuilder::createBuilderWithBaseValues()->build();

        $extension->load([$config], $container);

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'mappings' => ['XmlBundle' => []],
                    ],
                ],
            ])
            ->build();

        $container = $this->getContainer();
        $extension->load([$config], $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_mysql', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('root', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $args[1]);
        $this->assertCount(0, $definition->getMethodCalls());

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals(EntityManager::class, $definition->getClass());

        $this->assertNull($definition->getFactory());

        $this->assertEquals(['default' => 'doctrine.orm.default_entity_manager'], $container->getParameter('doctrine.entity_managers'), 'Set of the existing EntityManagers names is incorrect.');
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), 'Set of the existing EntityManagers names is incorrect.');

        $arguments = $definition->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $calls      = array_values($definition->getMethodCalls());

        $this->assertEquals(['XmlBundle' => 'Fixtures\Bundles\XmlBundle\Entity'], $calls[0][1][0]);
        $references = [];
        foreach ($calls as $call) {
            if (! ($call[1][0] instanceof Reference)) {
                continue;
            }

            $references[] = (string) $call[1][0];
        }

        self::assertContains('doctrine.orm.default_metadata_cache', $references);
        self::assertContains('doctrine.orm.default_query_cache', $references);
        self::assertContains('doctrine.orm.default_result_cache', $references);

        self::assertContains('doctrine.orm.naming_strategy.default', $references);
        self::assertContains('doctrine.orm.quote_strategy.default', $references);
        self::assertContains('doctrine.orm.typed_field_mapper.default', $references);
        self::assertContains('doctrine.orm.default_entity_listener_resolver', $references);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache_warmer');
        $this->assertSame(DoctrineMetadataCacheWarmer::class, $definition->getClass());
        $this->assertEquals(
            [
                new Reference('doctrine.orm.default_entity_manager'),
                '%kernel.build_dir%/doctrine/orm/default_metadata.php',
            ],
            $definition->getArguments(),
        );

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.default_metadata_cache'));
        $this->assertEquals(PhpArrayAdapter::class, $definition->getClass());

        $arguments = $definition->getArguments();
        $this->assertSame('%kernel.build_dir%/doctrine/orm/default_metadata.php', $arguments[0]);
        $wrappedDefinition = $arguments[1];
        $this->assertSame(ArrayAdapter::class, $wrappedDefinition->getClass());

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.default_query_cache'));
        $this->assertSame(ArrayAdapter::class, $definition->getClass());

        $definition = $container->getDefinition((string) $container->getAlias('doctrine.orm.default_result_cache'));
        $this->assertSame(ArrayAdapter::class, $definition->getClass());
    }

    public function testSingleEntityManagerWithDefaultConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $configurationArray = BundleConfigurationBuilder::createBuilderWithBaseValues()->build();

        $extension->load([$configurationArray], $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals(EntityManager::class, $definition->getClass());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);
    }

    /** @param array<mixed>|null $ormConfiguration */
    #[TestWith([[]])]
    #[TestWith([null])]
    public function testSingleEntityManagerWithEmptyConfiguration(array|null $ormConfiguration): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load([
            [
                'dbal' => [],
                'orm' => $ormConfiguration,
            ],
        ], $container);

        $this->assertEquals('default', $container->getParameter('doctrine.default_entity_manager'));
    }

    public function testSingleEntityManagerWithDefaultSecondLevelCacheConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $configurationArray = BundleConfigurationBuilder::createBuilderWithBaseValues()
            ->addBaseSecondLevelCache()
            ->build();

        $extension->load([$configurationArray], $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals(EntityManager::class, $definition->getClass());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);

        $slcDefinition = $container->getDefinition('doctrine.orm.default_second_level_cache.default_cache_factory');
        $this->assertEquals(DefaultCacheFactory::class, $slcDefinition->getClass());
    }

    #[IgnoreDeprecations]
    public function testSingleEntityManagerWithCustomSecondLevelCacheConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $configurationArray = BundleConfigurationBuilder::createBuilderWithBaseValues()
            ->addSecondLevelCache([
                'region_cache_driver' => ['type' => 'service', 'id' => 'my_cache'],
                'regions' => [
                    'hour_region' => ['lifetime' => 3600],
                ],
                'factory' => 'XmlBundle\Cache\MyCacheFactory',
            ])
            ->build();

        $extension->load([$configurationArray], $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals(EntityManager::class, $definition->getClass());

        $this->assertDICConstructorArguments($definition, [
            new Reference('doctrine.dbal.default_connection'),
            new Reference('doctrine.orm.default_configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
        ]);

        $slcDefinition = $container->getDefinition('doctrine.orm.default_second_level_cache.default_cache_factory');
        $this->assertEquals('XmlBundle\Cache\MyCacheFactory', $slcDefinition->getClass());
    }

    public function testBundleEntityAliases(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config        = BundleConfigurationBuilder::createBuilder()
             ->addBaseConnection()
             ->build();
        $config['orm'] = ['default_entity_manager' => 'default', 'entity_managers' => ['default' => ['mappings' => ['XmlBundle' => []]]]];
        $extension->load([$config], $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce(
            $definition,
            'setEntityNamespaces',
            [['XmlBundle' => 'Fixtures\Bundles\XmlBundle\Entity']],
        );
    }

    public function testOverwriteEntityAliases(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config        = BundleConfigurationBuilder::createBuilder()
             ->addBaseConnection()
             ->build();
        $config['orm'] = ['default_entity_manager' => 'default', 'entity_managers' => ['default' => ['mappings' => ['XmlBundle' => ['alias' => 'xml']]]]];
        $extension->load([$config], $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce(
            $definition,
            'setEntityNamespaces',
            [['xml' => 'Fixtures\Bundles\XmlBundle\Entity']],
        );
    }

    public function testOverrideDefaultEntityManagerWithAdditionalConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $extension->load([
            ['dbal' => [], 'orm' => ['default_entity_manager' => 'app', 'entity_managers' => ['app' => ['mappings' => ['XmlBundle' => ['alias' => 'xml']]]]]],
            ['orm' => ['metadata_cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']]],
        ], $container);

        $this->assertEquals('app', $container->getParameter('doctrine.default_entity_manager'));
    }

    public function testXmlBundleMappingDetection(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer(['XmlBundle']);
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'mappings' => [
                            'XmlBundle' => [],
                        ],
                    ],
                ],
            ])
            ->build();
        $extension->load([$config], $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', [
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity',
        ]);
    }

    public function testAttributesBundleMappingDetection(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer(['AttributesBundle']);
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'mappings' => [
                            'AttributesBundle' => ['type' => 'attribute'],
                        ],
                    ],
                ],
            ])
            ->build();
        $extension->load([$config], $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', [
            new Reference('doctrine.orm.default_attribute_metadata_driver'),
            'Fixtures\Bundles\AttributesBundle\Entity',
        ]);

        $attributeDriver = $container->get('doctrine.orm.default_attribute_metadata_driver');
        $this->assertInstanceOf(AttributeDriver::class, $attributeDriver);
    }

    public function testOrmMergeConfigs(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer(['XmlBundle', 'AttributesBundle']);
        $extension = new DoctrineExtension();

        $config1 = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'fetch_mode_subselect_batch_size' => 13,
                        'mappings' => ['AttributesBundle' => ['type' => 'attribute']],
                    ],
                ],
            ])
            ->build();
        $config2 = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'fetch_mode_subselect_batch_size' => 42,
                        'mappings' => [
                            'XmlBundle' => [],
                        ],
                    ],
                ],
            ])
            ->build();
        $extension->load([$config1, $config2], $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', [
            new Reference('doctrine.orm.default_attribute_metadata_driver'),
            'Fixtures\Bundles\AttributesBundle\Entity',
        ]);
        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', [
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity',
        ]);
    }

    public function testMessengerIntegration(): void
    {
        if (! interface_exists(MessageBusInterface::class)) {
            $this->markTestSkipped('Symfony Messenger component is not installed');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->build();
        $extension->load([$config], $container);

        $this->assertCount(1, $container->getDefinition('messenger.middleware.doctrine_transaction')->getArguments());
        $this->assertCount(1, $container->getDefinition('messenger.middleware.doctrine_ping_connection')->getArguments());
        $this->assertCount(1, $container->getDefinition('messenger.middleware.doctrine_close_connection')->getArguments());
        $this->assertCount(1, $container->getDefinition('doctrine.orm.messenger.event_subscriber.doctrine_clear_entity_manager')->getArguments());
    }

    public function testMessengerIntegrationWithDoctrineTransport(): void
    {
        if (! interface_exists(MessageBusInterface::class)) {
            $this->markTestSkipped('Symfony Messenger component is not installed');
        }

        if (! class_exists(DoctrineTransportFactory::class)) {
            $this->markTestSkipped('This test requires Symfony Messenger Doctrine transport to be installed');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
                ->addBaseConnection()
                ->build();
        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition('messenger.transport.doctrine.factory'));

        $messengerTransportDoctrineFactory = $container->getDefinition('messenger.transport.doctrine.factory');

        $this->assertCount(1, $messengerTransportDoctrineFactory->getArguments());
        $this->assertSame('doctrine', (string) $messengerTransportDoctrineFactory->getArgument(0));

        $this->assertSame(DoctrineTransportFactory::class, $messengerTransportDoctrineFactory->getClass());

        $this->assertTrue($messengerTransportDoctrineFactory->hasTag('messenger.transport_factory'));
        $this->assertContains('messenger.transport_factory', $container->findTags());
    }

    public function testMessengerIntegrationWithoutDoctrineTransport(): void
    {
        if (! interface_exists(MessageBusInterface::class)) {
            $this->markTestSkipped('Symfony Messenger component is not installed');
        }

        if (class_exists(DoctrineTransportFactory::class)) {
            $this->markTestSkipped('This test requires Symfony Messenger Doctrine transport to not be installed');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
                ->addBaseConnection()
                ->build();
        $extension->load([$config], $container);

        $this->assertFalse($container->hasDefinition('messenger.transport.doctrine.factory'));
        $this->assertFalse($container->hasDefinition('doctrine.orm.messenger.doctrine_schema_subscriber'));
        $this->assertFalse($container->hasDefinition('doctrine.orm.messenger.doctrine_schema_listener'));
        $this->assertNotContains('messenger.transport_factory', $container->findTags());
    }

    #[IgnoreDeprecations]
    public function testInvalidCacheConfiguration(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager(['metadata_cache_driver' => 'redis'])
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown cache of type "redis" configured for cache "metadata_cache" in entity manager "default"');

        $extension->load([$config], $container);
    }

    /** @param array{pool?: string, type: ?string, id?: string} $cacheConfig */
    #[DataProvider('cacheConfigurationProvider')]
    public function testCacheConfiguration(string $expectedAliasName, string $expectedTarget, string $cacheName, array $cacheConfig): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addEntityManager([$cacheName => $cacheConfig])
            ->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->hasAlias($expectedAliasName));
        $alias = $container->getAlias($expectedAliasName);
        $this->assertEquals($expectedTarget, (string) $alias);
    }

    /** @return array<string, array<string, string|array{type: ?string, pool?: string, id?: string}>> */
    public static function cacheConfigurationProvider(): array
    {
        return [
            'query_cache_default' => [
                'expectedAliasName' => 'doctrine.orm.default_query_cache',
                'expectedTarget' => 'cache.doctrine.orm.default.query',
                'cacheName' => 'query_cache_driver',
                'cacheConfig' => ['type' => null],
            ],
            'result_cache_default' => [
                'expectedAliasName' => 'doctrine.orm.default_result_cache',
                'expectedTarget' => 'cache.doctrine.orm.default.result',
                'cacheName' => 'result_cache_driver',
                'cacheConfig' => ['type' => null],
            ],
            'query_cache_pool' => [
                'expectedAliasName' => 'doctrine.orm.default_query_cache',
                'expectedTarget' => 'query_cache_pool',
                'cacheName' => 'query_cache_driver',
                'cacheConfig' => ['type' => 'pool', 'pool' => 'query_cache_pool'],
            ],
            'result_cache_pool' => [
                'expectedAliasName' => 'doctrine.orm.default_result_cache',
                'expectedTarget' => 'result_cache_pool',
                'cacheName' => 'result_cache_driver',
                'cacheConfig' => ['type' => 'pool', 'pool' => 'result_cache_pool'],
            ],
            'query_cache_service' => [
                'expectedAliasName' => 'doctrine.orm.default_query_cache',
                'expectedTarget' => 'service_target_query',
                'cacheName' => 'query_cache_driver',
                'cacheConfig' => ['type' => 'service', 'id' => 'service_target_query'],
            ],
            'result_cache_service' => [
                'expectedAliasName' => 'doctrine.orm.default_result_cache',
                'expectedTarget' => 'service_target_result',
                'cacheName' => 'result_cache_driver',
                'cacheConfig' => ['type' => 'service', 'id' => 'service_target_result'],
            ],
            'metadata_cache_default' => [
                'expectedAliasName' => 'doctrine.orm.default_metadata_cache',
                'expectedTarget' => 'cache.doctrine.orm.default.metadata',
                'cacheName' => 'metadata_cache_driver',
                'cacheConfig' => ['type' => null],
            ],
            'metadata_cache_pool' => [
                'expectedAliasName' => 'doctrine.orm.default_metadata_cache',
                'expectedTarget' => 'metadata_cache_pool',
                'cacheName' => 'metadata_cache_driver',
                'cacheConfig' => ['type' => 'pool', 'pool' => 'metadata_cache_pool'],
            ],
            'metadata_cache_service' => [
                'expectedAliasName' => 'doctrine.orm.default_metadata_cache',
                'expectedTarget' => 'service_target_metadata',
                'cacheName' => 'metadata_cache_driver',
                'cacheConfig' => ['type' => 'service', 'id' => 'service_target_metadata'],
            ],
        ];
    }

    /** @return array<array{0: class-string}> */
    public static function provideAttributeExcludedFromContainer(): array
    {
        return [
            'Embeddable' => [Embeddable::class],
            'Entity' => [Entity::class],
            'MappedSuperclass' => [MappedSuperclass::class],
        ];
    }

    #[DataProvider('provideAttributeExcludedFromContainer')]
    public function testEntityAttributeExcludesFromContainer(string $class): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addBaseEntityManager()
            ->build();

        $extension->load([$config], $container);

        /** @phpstan-ignore function.alreadyNarrowedType */
        $attributes = method_exists($container, 'getAttributeAutoconfigurators')
            ? array_map(static fn (array $arr) => $arr[0], $container->getAttributeAutoconfigurators())
            /** @phpstan-ignore method.notFound */
            : $container->getAutoconfiguredAttributes();
        $this->assertInstanceOf(Closure::class, $attributes[$class]);

        $definition = new ChildDefinition('');
        $attributes[$class]($definition);

        $this->assertSame([['source' => sprintf('with #[%s] attribute', $class)]], $definition->getTag('container.excluded'));
        $this->assertTrue($definition->isAbstract());
    }

    public function testAsEntityListenerAttribute(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addBaseEntityManager()
            ->build();

        $extension->load([$config], $container);

        /** @phpstan-ignore function.alreadyNarrowedType */
        $attributes = method_exists($container, 'getAttributeAutoconfigurators')
            ? array_map(static fn (array $arr) => $arr[0], $container->getAttributeAutoconfigurators())
            /** @phpstan-ignore method.notFound */
            : $container->getAutoconfiguredAttributes();
        $this->assertInstanceOf(Closure::class, $attributes[AsEntityListener::class]);

        $reflector  = new ReflectionClass(Php8EntityListener::class);
        $definition = new ChildDefinition('');
        $attribute  = $reflector->getAttributes(AsEntityListener::class)[0]->newInstance();

        $attributes[AsEntityListener::class]($definition, $attribute);

        $expected = [
            'event'          => null,
            'method'         => null,
            'lazy'           => null,
            'entity_manager' => null,
            'entity'         => null,
            'priority'       => null,
        ];
        $this->assertSame([$expected], $definition->getTag('doctrine.orm.entity_listener'));
    }

    public function testAsDoctrineListenerAttribute(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addBaseConnection()
            ->addBaseEntityManager()
            ->build();

        $extension->load([$config], $container);

        /** @phpstan-ignore function.alreadyNarrowedType */
        $attributes = method_exists($container, 'getAttributeAutoconfigurators')
            ? array_map(static fn (array $arr) => $arr[0], $container->getAttributeAutoconfigurators())
            /** @phpstan-ignore method.notFound */
            : $container->getAutoconfiguredAttributes();
        $this->assertInstanceOf(Closure::class, $attributes[AsDoctrineListener::class]);

        $reflector  = new ReflectionClass(Php8EventListener::class);
        $definition = new ChildDefinition('');
        $attribute  = $reflector->getAttributes(AsDoctrineListener::class)[0]->newInstance();

        $attributes[AsDoctrineListener::class]($definition, $attribute);

        $expected = [
            'event'      => Events::postFlush,
            'priority'   => null,
            'connection' => null,
        ];
        $this->assertSame([$expected], $definition->getTag('doctrine.event_listener'));
    }

    public function testRegistrationsWithMiddlewaresAndSfDebugMiddleware(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addConnection([
                'connections' => [
                    'conn1' => [
                        'password' => 'foo',
                        'logging' => true,
                        'profiling' => false,
                    ],
                    'conn2' => [
                        'password' => 'bar',
                        'logging' => false,
                        'profiling' => true,
                        'profiling_collect_backtrace' => false,
                    ],
                    'conn3' => [
                        'password' => 'bar',
                        'logging' => false,
                        'profiling' => true,
                        'profiling_collect_backtrace' => true,
                    ],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition('doctrine.dbal.logging_middleware'));

        $abstractMiddlewareDefTags      = $container->getDefinition('doctrine.dbal.logging_middleware')->getTags();
        $loggingMiddlewareTagAttributes = [];
        foreach ($abstractMiddlewareDefTags as $tag => $attributes) {
            if ($tag !== 'doctrine.middleware') {
                continue;
            }

            $loggingMiddlewareTagAttributes = $attributes;
        }

        $this->assertTrue(in_array(['connection' => 'conn1', 'priority' => 10], $loggingMiddlewareTagAttributes, true));
        $this->assertFalse(in_array(['connection' => 'conn2', 'priority' => 10], $loggingMiddlewareTagAttributes, true));
        $this->assertFalse(in_array(['connection' => 'conn3', 'priority' => 10], $loggingMiddlewareTagAttributes, true));

        $this->assertTrue($container->hasDefinition('doctrine.dbal.debug_middleware'));
        $this->assertTrue($container->hasDefinition('doctrine.debug_data_holder'));

        $abstractMiddlewareDefTags    = $container->getDefinition('doctrine.dbal.debug_middleware')->getTags();
        $debugMiddlewareTagAttributes = [];
        foreach ($abstractMiddlewareDefTags as $tag => $attributes) {
            if ($tag !== 'doctrine.middleware') {
                continue;
            }

            $debugMiddlewareTagAttributes = $attributes;
        }

        $this->assertFalse(in_array(['connection' => 'conn1', 'priority' => 10], $debugMiddlewareTagAttributes, true));
        $this->assertTrue(in_array(['connection' => 'conn2', 'priority' => 10], $debugMiddlewareTagAttributes, true));
        $this->assertTrue(in_array(['connection' => 'conn3', 'priority' => 10], $debugMiddlewareTagAttributes, true));

        $arguments = $container->getDefinition('doctrine.debug_data_holder')->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertSame(['conn3'], $arguments[0]);
    }

    public function testDefinitionsToLogAndProfile(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addConnection([
                'connections' => [
                    'conn1' => [
                        'password' => 'foo',
                        'logging' => true,
                        'profiling' => false,
                    ],
                    'conn2' => [
                        'password' => 'bar',
                        'logging' => false,
                        'profiling' => true,
                    ],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition('doctrine.dbal.logging_middleware'));

        $abstractMiddlewareDefTags      = $container->getDefinition('doctrine.dbal.logging_middleware')->getTags();
        $loggingMiddlewareTagAttributes = [];
        foreach ($abstractMiddlewareDefTags as $tag => $attributes) {
            if ($tag !== 'doctrine.middleware') {
                continue;
            }

            $loggingMiddlewareTagAttributes = $attributes;
        }

        $this->assertTrue(in_array(['connection' => 'conn1', 'priority' => 10], $loggingMiddlewareTagAttributes, true), 'Tag with connection conn1 not found for doctrine.dbal.logging_middleware');
        $this->assertFalse(in_array(['connection' => 'conn2'], $loggingMiddlewareTagAttributes, true), 'Tag with connection conn2 found for doctrine.dbal.logging_middleware');

        $abstractMiddlewareDefTags    = $container->getDefinition('doctrine.dbal.debug_middleware')->getTags();
        $debugMiddlewareTagAttributes = [];
        foreach ($abstractMiddlewareDefTags as $tag => $attributes) {
            if ($tag !== 'doctrine.middleware') {
                continue;
            }

            $debugMiddlewareTagAttributes = $attributes;
        }

        $this->assertFalse(in_array(['connection' => 'conn1', 'priority' => 10], $debugMiddlewareTagAttributes, true), 'Tag with connection conn1 found for doctrine.dbal.debug_middleware');
        $this->assertTrue(in_array(['connection' => 'conn2', 'priority' => 10], $debugMiddlewareTagAttributes, true), 'Tag with connection conn2 not found for doctrine.dbal.debug_middleware');
    }

    public function testDefinitionsToLogQueriesLoggingFalse(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addConnection([
                'connections' => [
                    'conn' => [
                        'password' => 'foo',
                        'logging' => false,
                    ],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition('doctrine.dbal.logging_middleware'));
        $abstractMiddlewareDefTags = $container->getDefinition('doctrine.dbal.logging_middleware')->getTags();
        $this->assertArrayNotHasKey('doctrine.middleware', $abstractMiddlewareDefTags);
    }

    #[RequiresMethod(Driver::class, '__construct')]
    public function testDefinitionsIdleConnection(): void
    {
        $container = $this->getContainer();
        $extension = new DoctrineExtension();

        $config = BundleConfigurationBuilder::createBuilder()
            ->addConnection([
                'connections' => [
                    'conn1' => [
                        'password' => 'foo',
                        'logging' => false,
                        'profiling' => false,
                        'idle_connection_ttl' => 15,
                    ],
                    'conn2' => [
                        'password' => 'bar',
                        'logging' => false,
                        'profiling' => true,
                    ],
                ],
            ])
            ->build();

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition('doctrine.dbal.idle_connection_middleware'));

        $abstractMiddlewareDef = $container->getDefinition('doctrine.dbal.idle_connection_middleware');
        $ttlByConnection       = $abstractMiddlewareDef->getArgument(1);

        $this->assertArrayHasKey('conn1', $ttlByConnection);
        $this->assertEquals(15, $ttlByConnection['conn1']);
        $this->assertArrayHasKey('conn2', $ttlByConnection);
        $this->assertEquals(600, $ttlByConnection['conn2']);

        $abstractMiddlewareDefTags = $container->getDefinition('doctrine.dbal.idle_connection_middleware')->getTags();

        $idleConnectionMiddlewareTagAttributes = [];
        foreach ($abstractMiddlewareDefTags as $tag => $attributes) {
            if ($tag !== 'doctrine.middleware') {
                continue;
            }

            $idleConnectionMiddlewareTagAttributes = $attributes;
        }

        $this->assertTrue(in_array(['connection' => 'conn1', 'priority' => 10], $idleConnectionMiddlewareTagAttributes, true), 'Tag with connection conn1 not found for doctrine.dbal.idle_connection_middleware');
        $this->assertTrue(in_array(['connection' => 'conn2', 'priority' => 10], $idleConnectionMiddlewareTagAttributes, true), 'Tag with connection conn2 found for doctrine.dbal.idle_connection_middleware');
    }

    #[IgnoreDeprecations]
    #[RequiresMethod(EntityValueResolver::class, '__construct')]
    #[TestWith([true])]
    #[TestWith([false])]
    public function testControllerResolver(bool $simpleEntityManagerConfig): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->getContainer();
        $extension = new DoctrineExtension();
        $config    = BundleConfigurationBuilder::createBuilderWithBaseValues()->build();

        if ($simpleEntityManagerConfig) {
            $config['orm'] = [];
        }

        $config['orm']['resolve_target_entities'] = ['Throwable' => 'stdClass'];

        $extension->load([$config], $container);

        $controllerResolver = $container->getDefinition('doctrine.orm.entity_value_resolver');

        $this->assertEquals([
            0 => new Reference('doctrine'),
            1 => new Reference('doctrine.orm.entity_value_resolver.expression_language', $container::IGNORE_ON_INVALID_REFERENCE),
            2 => (new Definition(MapEntity::class))->setArguments([
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                false,
            ]),
            3 => ['Throwable' => 'stdClass'],
        ], $controllerResolver->getArguments());

        $container = $this->getContainer();

        $config['orm']['controller_resolver'] = [
            'enabled' => false,
            'evict_cache' => true,
        ];
        $extension->load([$config], $container);

        $container->setDefinition('controller_resolver_defaults', $container->getDefinition('doctrine.orm.entity_value_resolver')->getArgument(2))->setPublic(true);
        $container->compile();
        $this->assertEquals(new MapEntity(null, null, null, null, null, null, null, true, true), $container->get('controller_resolver_defaults'));
    }

    /** @param list<string> $bundles */
    private static function getContainer(array $bundles = ['XmlBundle'], string $vendor = ''): ContainerBuilder
    {
        $map         = [];
        $metadataMap = [];
        foreach ($bundles as $bundle) {
            $bundleDir       = __DIR__ . '/Fixtures/Bundles/' . ($vendor ? $vendor . '/' : '') . $bundle;
            $bundleNamespace = 'Fixtures\\Bundles\\' . ($vendor ? $vendor . '\\' : '') . $bundle;

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
            'kernel.bundles_metadata' => $metadataMap,
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__ . '/../../', // src dir
        ]));

        // Register dummy cache services so we don't have to load the FrameworkExtension
        $container->setDefinition('cache.system', (new Definition(ArrayAdapter::class))->setPublic(true));
        $container->setDefinition('cache.app', (new Definition(ArrayAdapter::class))->setPublic(true));
        $container->setDefinition('my_pool', (new Definition(ArrayAdapter::class))->setPublic(true));
        $container->setDefinition('service_target_metadata_psr6', (new Definition(ArrayAdapter::class))->setPublic(true));

        return $container;
    }

    /** @param list<mixed> $args */
    private function assertDICConstructorArguments(Definition $definition, array $args): void
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '" . $definition->getClass() . "' don't match.");
    }

    /** @param list<mixed> $params */
    private function assertDICDefinitionMethodCallAt(int $pos, Definition $definition, string $methodName, array|null $params = null): void
    {
        $calls = $definition->getMethodCalls();
        if (! isset($calls[$pos][0])) {
            return;
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
    private function assertDICDefinitionMethodCallOnce(Definition $definition, string $methodName, array|null $params = null): void
    {
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

    private function compileContainer(ContainerBuilder $container): void
    {
        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();
    }
}

class TestWrapperClass extends Connection
{
}
