<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomServiceRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository;
use Fixtures\Bundles\RepositoryServiceBundle\RepositoryServiceBundle;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ServiceRepositoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (class_exists('Doctrine\\ORM\\Version')) {
            return;
        }

        $this->markTestSkipped('Doctrine ORM is not available.');
    }

    public function testRepositoryServiceWiring()
    {
        // needed for older versions of Doctrine
        AnnotationRegistry::registerFile(__DIR__ . '/../vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.name' => 'app',
            'kernel.debug' => false,
            'kernel.bundles' => ['RepositoryServiceBundle' => RepositoryServiceBundle::class],
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__ . '/../../../../', // src dir
        ]));
        $container->set('annotation_reader', new AnnotationReader());
        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([[
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'charset' => 'UTF8',
            ],
            'orm' => [
                'mappings' => [
            'RepositoryServiceBundle' => [
                    'type' => 'annotation',
                    'dir' => __DIR__ . '/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/Entity',
                    'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Entity',
                ],
                ],
            ],
        ],
        ], $container);

        $def = $container->register(TestCustomServiceRepoRepository::class, TestCustomServiceRepoRepository::class)
            ->setPublic(false);
        // create a public alias so we can use it below for testing
        $container->setAlias('test_alias__' . TestCustomServiceRepoRepository::class, new Alias(TestCustomServiceRepoRepository::class, true));

        // Symfony 2.7 compat - can be moved above later
        if (method_exists($def, 'setAutowired')) {
            $def->setAutowired(true);
        }

        // Symfony 3.3 and higher: autowire definition so it receives the tags
        if (class_exists(ServiceLocatorTagPass::class)) {
            $def->setAutoconfigured(true);
        }

        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
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

        // Symfony 3.2 and lower should work normally in traditional cases (tested above)
        // the code below should *not* work (by design)
        if (! class_exists(ServiceLocatorTagPass::class)) {
            $message = '/Support for loading entities from the service container only works for Symfony 3\.3/';
            if (method_exists($this, 'expectException')) {
                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessageRegExp($message);
            } else {
                // PHPUnit 4 compat
                $this->setExpectedExceptionRegExp(\RuntimeException::class, $message);
            }
        }

        // custom service repository
        $customServiceRepo = $em->getRepository(TestCustomServiceRepoEntity::class);
        $this->assertSame($customServiceRepo, $container->get('test_alias__' . TestCustomServiceRepoRepository::class));
        // a smoke test, trying some methods
        $this->assertSame(TestCustomServiceRepoEntity::class, $customServiceRepo->getClassName());
        $this->assertInstanceOf(QueryBuilder::class, $customServiceRepo->createQueryBuilder('tc'));
    }
}
