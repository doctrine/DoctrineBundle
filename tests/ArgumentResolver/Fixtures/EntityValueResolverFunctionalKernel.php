<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Configuration;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function md5;
use function method_exists;
use function mt_rand;
use function sys_get_temp_dir;
use function version_compare;

use const PHP_VERSION_ID;

class EntityValueResolverFunctionalKernel extends Kernel
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
            'router' => ['utf8' => true],
            'test' => true,
        /** @phpstan-ignore ternary.alwaysFalse */
        ] + (version_compare(Kernel::VERSION, '7.0.0', '<') ? ['annotations' => false] : []));

        $container->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'schema_manager_factory' => 'doctrine.dbal.default_schema_manager_factory',
            ],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                /** @phpstan-ignore function.alreadyNarrowedType,greaterOrEqual.alwaysTrue */
                'enable_native_lazy_objects' => PHP_VERSION_ID >= 80400 && method_exists(Configuration::class, 'enableNativeLazyObjects'),
                'mappings' => [
                    'PostFixtures' => [
                        'type' => 'attribute',
                        'dir' => __DIR__,
                        'prefix' => 'Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures',
                    ],
                ],
            ],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(PostController::class)
            ->autowire()
            ->autoconfigure()
            ->public()
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('post_show', '/posts/{post}')->controller(PostController::class);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir ??= sys_get_temp_dir() . '/sf_evr_kernel_' . md5((string) mt_rand());
    }
}
