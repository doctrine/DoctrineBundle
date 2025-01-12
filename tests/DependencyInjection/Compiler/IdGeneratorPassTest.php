<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\CustomIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Fixtures\Bundles\AttributesBundle\AttributesBundle;
use Fixtures\Bundles\AttributesBundle\Entity\TestCustomIdGeneratorEntity as AttributeCustomIdGeneratorEntity;
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
        $bundles  = ['AttributesBundle' => AttributesBundle::class];
        $entity   = AttributeCustomIdGeneratorEntity::class;
        $mappings = [
            'AttributesBundle' => [
                'type' => 'attribute',
                'dir' => __DIR__ . '/../Fixtures/Bundles/AttributesBundle/Entity',
                'prefix' => 'Fixtures\Bundles\AttributesBundle\Entity',
            ],
        ];

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => $bundles,
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.runtime_environment' => '%%env(default:kernel.environment:APP_RUNTIME_ENV)%%',
            'kernel.root_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.project_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.bundles_metadata' => [],
            'kernel.charset' => 'UTF-8',
            'kernel.container_class' => ContainerBuilder::class,
            'kernel.secret' => 'test',
            'container.build_id' => uniqid(),
            'env(base64:default::SYMFONY_DECRYPTION_SECRET)' => 'foo',
            'env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)' => '',
            'env(default::SYMFONY_TRUSTED_HOSTS)' => '',
            'env(default::SYMFONY_TRUSTED_PROXIES)' => '',
            'env(default::SYMFONY_TRUSTED_HEADERS)' => '',
            'debug.file_link_format' => null,
        ]));

        $extension = new FrameworkExtension();
        $container->registerExtension($extension);
        $extension->load([
            'framework' => [
                'http_method_override' => false,
                'annotations' => ['enabled' => false],
                'php_errors' => ['log' => true],
                'handle_all_throwables' => true,
            ],
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
                    'mappings' => $mappings,
                    'report_fields_where_declared' => true,
                    'enable_lazy_ghost_objects' => true,
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

        $metadata = $em->getClassMetadata($entity);
        $this->assertInstanceOf(CustomIdGenerator::class, $metadata->idGenerator);
    }
}
