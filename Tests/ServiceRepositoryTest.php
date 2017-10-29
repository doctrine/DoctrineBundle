<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Repository\DefaultServiceRepository;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\RepositoryServiceBundle;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomRepoRepository;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

class ServiceRepositoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Doctrine\\ORM\\Version')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }
    }

    public function testRepositoryServiceWiring()
    {
        if (!class_exists(ServiceLocatorTagPass::class)) {
            $this->markTestSkipped('Symfony 3.3 or higher is needed for this feature.');
        }

        $container = new ContainerBuilder(new ParameterBag(array(
            'kernel.name' => 'app',
            'kernel.debug' => false,
            'kernel.bundles' => array('RepositoryServiceBundle' => RepositoryServiceBundle::class),
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__.'/../../../../', // src dir
        )));
        $container->set('annotation_reader', new AnnotationReader());
        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load(array(array(
            'dbal' => array(
                'driver' => 'pdo_mysql',
                'charset' => 'UTF8',
            ),
            'orm' => array(
                'mappings' => array('RepositoryServiceBundle' => array(
                    'type' => 'annotation',
                    'dir' => __DIR__.'/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/Entity',
                    'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Entity',
                )),
                'use_service_repositories' => true,
            ),
        )), $container);

        $container->register(TestCustomRepoRepository::class)
            ->setAutowired(true)
            ->setPublic(false)
            ->addTag('doctrine.repository_service');

        $container->getCompilerPassConfig()->addPass(new ServiceRepositoryCompilerPass());
        $container->compile();

        $em = $container->get('doctrine.orm.default_entity_manager');
        $customRepo = $em->getRepository(TestCustomRepoEntity::class);
        $this->assertSame($customRepo, $container->get(TestCustomRepoRepository::class));
        // a smoke test, trying some methods
        $this->assertSame(TestCustomRepoEntity::class, $customRepo->getClassName());
        $this->assertInstanceOf(QueryBuilder::class, $customRepo->createQueryBuilder('tc'));

        $genericRepository = $em->getRepository(TestDefaultRepoEntity::class);
        $this->assertInstanceOf(EntityRepository::class, $genericRepository);
        $this->assertSame($genericRepository, $genericRepository = $em->getRepository(TestDefaultRepoEntity::class));
        // a smoke test, trying one of the methods
        $this->assertSame(TestDefaultRepoEntity::class, $genericRepository->getClassName());
    }
}
