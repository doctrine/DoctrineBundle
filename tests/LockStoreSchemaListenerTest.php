<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\SchemaListener\LockStoreSchemaListener;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Kernel;

use function class_exists;
use function interface_exists;
use function sys_get_temp_dir;

class LockStoreSchemaListenerTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @testWith [{}, 0]
     *           [{"lock": "flock"}, 1]
     */
    public function testLockStoreSchemaSubscriberWiring(array $config, int $expectedCount): void
    {
        if (! class_exists(LockStoreSchemaListener::class)) {
            self::markTestSkipped('symfony/doctrine-bridge version not supported');
        }

        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => [],
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.runtime_environment' => '%%env(default:kernel.environment:APP_RUNTIME_ENV)%%',
            'kernel.build_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.root_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.project_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.bundles_metadata' => [],
            'kernel.charset' => 'UTF-8',
            'kernel.container_class' => ContainerBuilder::class,
            'kernel.secret' => 'test',
            'env(base64:default::SYMFONY_DECRYPTION_SECRET)' => 'foo',
            'debug.file_link_format' => null,
        ]));

        $extension = new FrameworkExtension();
        $container->registerExtension($extension);
        $extension->load(
            [
                'framework' => ['http_method_override' => false, 'php_errors' => ['log' => true]]
                + (Kernel::VERSION_ID >= 60200 ? ['handle_all_throwables' => true] : []) + $config,
            ],
            $container,
        );

        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([['dbal' => [], 'orm' => []]], $container);

        $container->setAlias(
            'test_subscriber_lock_alias',
            new Alias('doctrine.orm.listeners.lock_store_schema_listener', true),
        );
        $container->compile();

        $this->assertCount(
            $expectedCount,
            $container->findDefinition('test_subscriber_lock_alias')->getArguments()[0]->getValues(),
        );
    }
}
