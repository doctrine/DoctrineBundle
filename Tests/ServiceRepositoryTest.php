<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomServiceRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository;
use Fixtures\Bundles\RepositoryServiceBundle\RepositoryServiceBundle;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Kernel;

use function class_exists;
use function interface_exists;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_VERSION_ID;

class ServiceRepositoryTest extends TestCase
{
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

    public function testRepositoryServiceWiring(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => ['RepositoryServiceBundle' => RepositoryServiceBundle::class],
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
            'container.build_id' => uniqid(),
            'env(base64:default::SYMFONY_DECRYPTION_SECRET)' => 'foo',
            'debug.file_link_format' => null,
        ]));

        if (class_exists(AnnotationReader::class)) {
            $container->set('annotation_reader', new AnnotationReader());
        }

        $extension = new FrameworkExtension();
        $container->registerExtension($extension);
        $extension->load([
            'framework' => [
                'http_method_override' => false,
                'php_errors' => ['log' => true],
                'annotations' => [
                    'enabled' => class_exists(AnnotationReader::class) && Kernel::VERSION_ID < 60400,
                ],
            ] + (Kernel::VERSION_ID >= 60200 ? ['handle_all_throwables' => true] : []),
        ], $container);

        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([
            [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'charset' => 'UTF8',
                    'schema_manager_factory' => 'doctrine.dbal.default_schema_manager_factory',
                ],
                'orm' => [
                    'report_fields_where_declared' => true,
                    'enable_lazy_ghost_objects' => PHP_VERSION_ID >= 80100,
                    'mappings' => [
                        'RepositoryServiceBundle' => [
                            'type' => PHP_VERSION_ID >= 80000 ? 'attribute' : 'annotation',
                            'dir' => __DIR__ . '/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/Entity',
                            'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Entity',
                        ],
                    ],
                ],
            ],
        ], $container);

        $def = $container->register(TestCustomServiceRepoRepository::class, TestCustomServiceRepoRepository::class)
            ->setPublic(false);
        // create a public alias, so we can use it below for testing
        $container->setAlias('test_alias__' . TestCustomServiceRepoRepository::class, new Alias(TestCustomServiceRepoRepository::class, true));

        $def->setAutowired(true);
        $def->setAutoconfigured(true);

        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
        $container->addCompilerPass(new CacheCompatibilityPass());
        $container->compile();

        $em = $container->get('doctrine.orm.default_entity_manager');

        // traditional custom class repository
        $customClassRepo = $em->getRepository(TestCustomClassRepoEntity::class);
        $this->assertInstanceOf(TestCustomClassRepoRepository::class, $customClassRepo);
        // a smoke test, trying some methods
        $this->assertSame(TestCustomClassRepoEntity::class, $customClassRepo->getClassName());
        $this->assertInstanceOf(QueryBuilder::class, $customClassRepo->createQueryBuilder('tc'));

        // generic EntityRepository
        $genericRepository = $em->getRepository(TestDefaultRepoEntity::class);
        $this->assertInstanceOf(EntityRepository::class, $genericRepository);
        $this->assertSame($genericRepository, $genericRepository = $em->getRepository(TestDefaultRepoEntity::class));
        // a smoke test, trying one of the methods
        $this->assertSame(TestDefaultRepoEntity::class, $genericRepository->getClassName());

        // custom service repository
        $customServiceRepo = $em->getRepository(TestCustomServiceRepoEntity::class);
        $this->assertSame($customServiceRepo, $container->get('test_alias__' . TestCustomServiceRepoRepository::class));
        // a smoke test, trying some methods
        $this->assertSame(TestCustomServiceRepoEntity::class, $customServiceRepo->getClassName());
        $this->assertInstanceOf(QueryBuilder::class, $customServiceRepo->createQueryBuilder('tc'));
    }
}
