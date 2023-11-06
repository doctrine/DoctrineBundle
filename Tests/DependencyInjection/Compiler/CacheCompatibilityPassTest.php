<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\Cache\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function class_exists;
use function get_class;
use function interface_exists;

use const PHP_VERSION_ID;

class CacheCompatibilityPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    public static function setUpBeforeClass(): void
    {
        if (PHP_VERSION_ID < 80000 && ! class_exists(AnnotationReader::class)) {
            self::markTestSkipped('This test requires Annotations when run on PHP 7');
        }

        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testCacheConfigUsingServiceDefinedByApplication(): void
    {
        $customRegionClass = get_class($this->createMock(Region::class));

        (new class ($customRegionClass) extends TestKernel {
            private string $regionClass;

            public function __construct(string $regionClass)
            {
                parent::__construct(false);
                $this->regionClass = $regionClass;
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(function (ContainerBuilder $containerBuilder): void {
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
                                        'filelock' => ['type' => 'filelock', 'lifetime' => 0, 'cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']],
                                        'lifelong' => ['lifetime' => 0, 'cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']],
                                        'entity_cache_region' => ['type' => 'service', 'service' => $this->regionClass],
                                    ],
                                ],
                            ],
                        ],
                    );
                    $containerBuilder->register($this->regionClass, $this->regionClass);
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        new Definition(ArrayAdapter::class),
                    );
                });
            }
        })->boot();

        $this->addToAssertionCount(1);
    }

    public function testMetadataCacheConfigUsingPsr6ServiceDefinedByApplication(): void
    {
        (new class (false) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        ['orm' => ['metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service']]],
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        new Definition(ArrayAdapter::class),
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
                        ['orm' => ['metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service']]],
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        (new Definition(DoctrineProvider::class))
                            ->setArguments([new Definition(ArrayAdapter::class)])
                            ->setFactory([DoctrineProvider::class, 'wrap']),
                    );
                });
            }
        })->boot();
    }
}
