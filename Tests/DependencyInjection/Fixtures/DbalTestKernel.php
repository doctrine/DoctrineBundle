<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCaseAllPublicCompilerPass;
use Doctrine\Common\Annotations\Annotation;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

use function class_exists;
use function md5;
use function mt_rand;
use function sys_get_temp_dir;

class DbalTestKernel extends Kernel
{
    /** @var array<string, mixed> */
    private array $dbalConfig;

    private ?string $projectDir = null;

    /** @param array<string, mixed> $dbalConfig */
    public function __construct(array $dbalConfig = ['driver' => 'pdo_sqlite'])
    {
        $this->dbalConfig = $dbalConfig;

        parent::__construct('test', true);
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
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'F00',
                'http_method_override' => false,
                'annotations' => [
                    'enabled' => class_exists(Annotation::class),
                ],
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => $this->dbalConfig,
            ]);

            // Register a NullLogger to avoid getting the stderr default logger of FrameworkBundle
            $container->register('logger', NullLogger::class);

            // make all Doctrine services public, so we can fetch them in the test
            $container->getCompilerPassConfig()->addPass(new TestCaseAllPublicCompilerPass());
        });
    }

    public function getProjectDir(): string
    {
        return $this->projectDir ??= sys_get_temp_dir() . '/sf_kernel_' . md5((string) mt_rand());
    }

    public function getRootDir(): string
    {
        return $this->getProjectDir();
    }
}
