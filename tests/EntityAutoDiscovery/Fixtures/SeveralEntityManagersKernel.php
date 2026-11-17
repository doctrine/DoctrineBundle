<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Configuration;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

use function md5;
use function method_exists;
use function mt_rand;
use function sys_get_temp_dir;
use function version_compare;

use const PHP_VERSION_ID;

/**
 * Two entity managers: "default" configures no mapping and is listed first,
 * "tenant" maps the Tenant namespace on its own connection.
 */
class SeveralEntityManagersKernel extends Kernel
{
    use MicroKernelTrait;

    private string|null $projectDir = null;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    /** @return iterable<BundleInterface> */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'F00',
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'handle_all_throwables' => true,
            'test' => true,
        /** @phpstan-ignore ternary.alwaysFalse */
        ] + (version_compare(Kernel::VERSION, '7.0.0', '<') ? ['annotations' => false] : []));

        $connection = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'schema_manager_factory' => 'doctrine.dbal.default_schema_manager_factory',
        ];

        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => $connection,
                    'tenant' => $connection,
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'controller_resolver' => ['auto_mapping' => false],
                /** @phpstan-ignore function.alreadyNarrowedType,greaterOrEqual.alwaysTrue */
                'enable_native_lazy_objects' => PHP_VERSION_ID >= 80400 && method_exists(Configuration::class, 'enableNativeLazyObjects'),
                'entity_managers' => [
                    'default' => ['connection' => 'default'],
                    'tenant' => [
                        'connection' => 'tenant',
                        'mappings' => [
                            'Tenant' => [
                                'type' => 'attribute',
                                'dir' => __DIR__ . '/Tenant',
                                'prefix' => 'Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures\Tenant',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->services()->set('logger', NullLogger::class);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir ??= sys_get_temp_dir() . '/sf_several_em_kernel_' . md5((string) mt_rand());
    }
}
