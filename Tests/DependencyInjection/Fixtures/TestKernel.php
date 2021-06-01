<?php

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
    /** @var string|null */
    private $projectDir;

    public function __construct(bool $debug = true)
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
            $container->loadFromExtension('framework', ['secret' => 'F00']);

            $container->loadFromExtension('doctrine', [
                'dbal' => ['driver' => 'pdo_sqlite'],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'mappings' => [
                        'RepositoryServiceBundle' => [
                            'type' => 'annotation',
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
        if ($this->projectDir === null) {
            $this->projectDir = sys_get_temp_dir() . '/sf_kernel_' . md5((string) mt_rand());
        }

        return $this->projectDir;
    }

    public function getRootDir(): string
    {
        return $this->getProjectDir();
    }
}
