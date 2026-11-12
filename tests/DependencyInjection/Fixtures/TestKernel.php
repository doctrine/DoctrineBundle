<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

use function md5;
use function mt_rand;
use function sys_get_temp_dir;

class TestKernel extends Kernel
{
    public function __construct(bool $debug = true, private string|null $projectDir = null)
    {
        parent::__construct('test', $debug);
    }

    /** @return iterable<Bundle> */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'F00',
                'http_method_override' => false,
                'php_errors' => ['log' => true],
                'handle_all_throwables' => true,
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'schema_manager_factory' => 'doctrine.dbal.default_schema_manager_factory',
                ],
                'orm' => [
                    'mappings' => [
                        'RepositoryServiceBundle' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/Bundles/RepositoryServiceBundle/Entity',
                            'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Entity',
                        ],
                    ],
                ],
            ]);

            // Register a NullLogger to avoid getting the stderr default logger of FrameworkBundle
            $container->register('logger', NullLogger::class);
        });
    }

    public function getProjectDir(): string
    {
        return $this->projectDir ??= sys_get_temp_dir() . '/sf_kernel_' . md5((string) mt_rand());
    }
}
