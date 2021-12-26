<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\CustomIdGenerator;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Fixtures\Bundles\AnnotationsBundle\AnnotationsBundle;
use Fixtures\Bundles\AnnotationsBundle\Entity\TestCustomIdGeneratorEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

use function assert;
use function interface_exists;
use function sys_get_temp_dir;
use function uniqid;

class IdGeneratorPassTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testRepositoryServiceWiring(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => ['AnnotationsBundle' => AnnotationsBundle::class],
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
        $container->set('annotation_reader', new AnnotationReader());

        $extension = new FrameworkExtension();
        $container->registerExtension($extension);
        $extension->load(['framework' => []], $container);

        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([
            [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'charset' => 'UTF8',
                ],
                'orm' => [
                    'mappings' => [
                        'AnnotationsBundle' => [
                            'type' => 'annotation',
                            'dir' => __DIR__ . '/../Fixtures/Bundles/AnnotationsBundle/Entity',
                            'prefix' => 'Fixtures\Bundles\AnnotationsBundle\Entity',
                        ],
                    ],
                ],
            ],
        ], $container);

        $def = $container->register('my_id_generator', CustomIdGenerator::class);

        $def->setAutoconfigured(true);

        $container->addCompilerPass(new CacheCompatibilityPass());
        $container->addCompilerPass(new IdGeneratorPass());
        $container->compile();

        $em = $container->get('doctrine.orm.default_entity_manager');
        assert($em instanceof EntityManagerInterface);

        $metadata = $em->getClassMetadata(TestCustomIdGeneratorEntity::class);
        $this->assertInstanceOf(CustomIdGenerator::class, $metadata->idGenerator);
    }
}
