<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Security\Core\User\UserInterface;

use function class_exists;
use function sys_get_temp_dir;
use function uniqid;

class TestCase extends BaseTestCase
{
    public function createXmlBundleTestContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.bundles' => ['XmlBundle' => 'Fixtures\Bundles\XmlBundle\XmlBundle'],
            'kernel.cache_dir' => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.project_dir' => __DIR__ . '/../../../../', // src dir
            'kernel.bundles_metadata' => [],
            'container.build_id' => uniqid(),
        ]));

        if (class_exists(AnnotationReader::class)) {
            $container->set('annotation_reader', new AnnotationReader());
        }

        $extension = new DoctrineExtension();
        $container->registerExtension($extension);
        $extension->load([
            [
                'dbal' => [
                    'connections' => [
                        'default' => [
                            'driver' => 'pdo_sqlite',
                            'charset' => 'UTF8',
                            'schema_manager_factory' => 'doctrine.dbal.default_schema_manager_factory',
                        ],
                    ],
                    'default_connection' => 'default',
                    'types' => [
                        'test' => [
                            'class' => TestType::class,
                        ],
                    ],
                ],
                'orm' => [
                    'default_entity_manager' => 'default',
                    'entity_managers' => [
                        'default' => [
                            'validate_xml_mapping' => true,
                            'mappings' => [
                                'XmlBundle' => [
                                    'type' => 'xml',
                                    'dir' => __DIR__ . '/DependencyInjection/Fixtures/Bundles/XmlBundle/Resources/config/doctrine',
                                    'prefix' => 'Fixtures\Bundles\XmlBundle\Entity',
                                ],
                            ],
                        ],
                    ],
                    'resolve_target_entities' => [UserInterface::class => 'stdClass'],
                ],
            ],
        ], $container);

        // Register dummy cache services so we don't have to load the FrameworkExtension
        $container->setDefinition('cache.system', (new Definition(ArrayAdapter::class))->setPublic(true));
        $container->setDefinition('cache.app', (new Definition(ArrayAdapter::class))->setPublic(true));

        $compilerPassConfig = $container->getCompilerPassConfig();

        $compilerPassConfig->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $compilerPassConfig->setRemovingPasses([]);
        $compilerPassConfig->addPass(new CacheCompatibilityPass());
        // make all Doctrine services public, so we can fetch them in the test
        $compilerPassConfig->addPass(new TestCaseAllPublicCompilerPass());
        $container->compile();

        return $container;
    }
}
