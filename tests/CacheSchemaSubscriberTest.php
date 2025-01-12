<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheSchemaSubscriberPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

use function interface_exists;
use function sys_get_temp_dir;

class CacheSchemaSubscriberTest extends TestCase
{
    public function testSchemaSubscriberWiring(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => [],
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.runtime_environment' => '%%env(default:kernel.environment:APP_RUNTIME_ENV)%%',
            'kernel.root_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.project_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.bundles_metadata' => [],
            'kernel.charset' => 'UTF-8',
            'kernel.container_class' => ContainerBuilder::class,
            'kernel.secret' => 'test',
            'env(base64:default::SYMFONY_DECRYPTION_SECRET)' => 'foo',
            'env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)' => '',
            'env(default::SYMFONY_TRUSTED_HOSTS)' => '',
            'env(default::SYMFONY_TRUSTED_PROXIES)' => '',
            'env(default::SYMFONY_TRUSTED_HEADERS)' => '',
            'debug.file_link_format' => null,
        ]));

        $extension = new FrameworkExtension();
        $container->registerExtension($extension);
        $extension->load([
            'framework' => [
                'http_method_override' => false,
                'cache' => [
                    'pools' => [
                        'my_cache_adapter' => ['adapter' => 'cache.adapter.doctrine_dbal'],
                    ],
                ],
            ],
        ], $container);

        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([
            [
                'dbal' => [],
                'orm' => [],
            ],
        ], $container);

        $container->setAlias('test_subscriber_alias', new Alias('doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener', true));
        // prevent my_cache_adapter from inlining
        $container->register('uses_my_cache_adapter', 'stdClass')
            ->addArgument(new Reference('my_cache_adapter'))
            ->setPublic(true);
        $container->addCompilerPass(new CacheSchemaSubscriberPass(), PassConfig::TYPE_BEFORE_REMOVING, -10);
        $container->compile();

        // check that PdoAdapter service is injected as an argument
        $definition = $container->findDefinition('test_subscriber_alias');
        $this->assertEquals([new Reference('my_cache_adapter')], $definition->getArgument(0));
    }
}
