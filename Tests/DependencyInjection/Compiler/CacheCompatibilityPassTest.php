<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CacheCompatibilityPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testCacheConfigUsingServiceDefinedByApplication(): void
    {
        (new class () extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension('framework', [
                        'cache' => [
                            'pools' => [
                                'doctrine.system_cache_pool' => ['adapter' => 'cache.system'],
                            ],
                        ],
                    ]);
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        [
                            'orm' => [
                                'query_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service'],
                                'result_cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool'],
                                'second_level_cache' => [
                                    'enabled' => true,
                                    'regions' => [
                                        'lifelong' => ['lifetime' => 0, 'cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']],
                                    ],
                                ],
                            ],
                        ]
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        (new Definition(DoctrineProvider::class))
                            ->setArguments([new Definition(ArrayAdapter::class)])
                            ->setFactory([DoctrineProvider::class, 'wrap'])
                    );
                });
            }
        })->boot();

        $this->addToAssertionCount(1);
    }

    /** @group legacy */
    public function testMetadataCacheConfigUsingPsr6ServiceDefinedByApplication(): void
    {
        $this->expectDeprecation('%aThe "metadata_cache_driver" configuration key is deprecated.%a');
        (new class (false) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        ['orm' => ['metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service']]]
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        new Definition(ArrayAdapter::class)
                    );
                });
            }
        })->boot();
    }

    /** @group legacy */
    public function testMetadataCacheConfigUsingNonPsr6ServiceDefinedByApplication(): void
    {
        $this->expectDeprecation('Since doctrine/doctrine-bundle 2.4: Configuring doctrine/cache is deprecated. Please update the cache service "custom_cache_service" to use a PSR-6 cache.');
        (new class (false) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        ['orm' => ['metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service']]]
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        (new Definition(DoctrineProvider::class))
                            ->setArguments([new Definition(ArrayAdapter::class)])
                            ->setFactory([DoctrineProvider::class, 'wrap'])
                    );
                });
            }
        })->boot();
    }
}
