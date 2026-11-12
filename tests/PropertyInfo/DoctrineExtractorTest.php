<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\PropertyInfo;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\PropertyInfo\Fixtures\AliasClassMetadataFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\PropertyInfo\Fixtures\TestEmbeddable;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCaseAllPublicCompilerPass;
use Doctrine\ORM\EntityManagerInterface;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function interface_exists;
use function method_exists;
use function restore_exception_handler;

class DoctrineExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testUnmappedClassesDoNotInitializeTheEntityManager(): void
    {
        $container = self::bootKernel([]);
        $extractor = $container->get('doctrine.orm.default_entity_manager.property_info_extractor');
        $em        = $container->get('doctrine.orm.default_entity_manager');

        $this->assertInstanceOf(DoctrineExtractor::class, $extractor);
        $this->assertNull($extractor->getProperties(TestCustomClassRepoRepository::class));
        $this->assertNull($extractor->isWritable(TestCustomClassRepoRepository::class, 'id'));
        $this->assertNull($extractor->getProperties('Fixtures\Bundles\RepositoryServiceBundle\Entity\Missing'));
        $this->assertTrue(self::isUninitialized($em));

        $this->assertSame(['id'], $extractor->getProperties(TestDefaultRepoEntity::class));
        $this->assertFalse($extractor->isWritable(TestDefaultRepoEntity::class, 'id'));
        $this->assertFalse(self::isUninitialized($em));
        $this->assertNull($extractor->getProperties(TestCustomClassRepoRepository::class));

        restore_exception_handler();
    }

    public function testGetTypeOfUnmappedClassesDoesNotInitializeTheEntityManager(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (! method_exists(DoctrineExtractor::class, 'getType')) {
            self::markTestSkipped('This test requires symfony/doctrine-bridge 7.1 or higher');
        }

        $container = self::bootKernel([]);
        $extractor = $container->get('doctrine.orm.default_entity_manager.property_info_extractor');
        $em        = $container->get('doctrine.orm.default_entity_manager');

        $this->assertInstanceOf(DoctrineExtractor::class, $extractor);
        $this->assertNull($extractor->getType(TestCustomClassRepoRepository::class, 'id'));
        $this->assertTrue(self::isUninitialized($em));

        $this->assertEquals(Type::int(), $extractor->getType(TestDefaultRepoEntity::class, 'id'));
        $this->assertFalse(self::isUninitialized($em));

        restore_exception_handler();
    }

    /**
     * @param array<string, mixed> $orm
     * @param list<string>         $properties
     */
    #[DataProvider('provideClassesTheEntityManagerResolves')]
    public function testClassesTheEntityManagerResolves(array $orm, string $class, array $properties): void
    {
        $container = self::bootKernel($orm);
        $extractor = $container->get('doctrine.orm.default_entity_manager.property_info_extractor');

        $this->assertInstanceOf(DoctrineExtractor::class, $extractor);
        $this->assertSame($properties, $extractor->getProperties($class));

        restore_exception_handler();
    }

    /** @return iterable<string, array{array<string, mixed>, string, list<string>}> */
    public static function provideClassesTheEntityManagerResolves(): iterable
    {
        yield 'leading backslash' => [[], '\\' . TestDefaultRepoEntity::class, ['id']];
        yield 'proxy' => [[], 'Proxies\\__CG__\\' . TestDefaultRepoEntity::class, ['id']];
        yield 'resolved target entity' => [['resolve_target_entities' => [UserInterface::class => TestDefaultRepoEntity::class]], UserInterface::class, ['id']];
        yield 'custom metadata factory' => [['class_metadata_factory_name' => AliasClassMetadataFactory::class], stdClass::class, ['id']];

        $mappings = [
            'PropertyInfo' => [
                'type' => 'attribute',
                'dir' => __DIR__ . '/Fixtures',
                'prefix' => 'Doctrine\\Bundle\\DoctrineBundle\\Tests\\PropertyInfo\\Fixtures',
            ],
        ];

        yield 'embeddable' => [['mappings' => $mappings], TestEmbeddable::class, ['value']];
    }

    /** @param array<string, mixed> $orm */
    private static function bootKernel(array $orm): ContainerInterface
    {
        $kernel = new class ($orm) extends TestKernel {
            /** @param array<string, mixed> $orm */
            public function __construct(private array $orm)
            {
                parent::__construct();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);

                $loader->load(function (ContainerBuilder $container): void {
                    $container->loadFromExtension('doctrine', ['orm' => $this->orm]);
                });
            }

            protected function build(ContainerBuilder $container): void
            {
                $container->addCompilerPass(new TestCaseAllPublicCompilerPass());
            }
        };
        $kernel->boot();

        return $kernel->getContainer();
    }

    private static function isUninitialized(object $manager): bool
    {
        if ($manager instanceof LazyObjectInterface) {
            return ! $manager->isLazyObjectInitialized();
        }

        return (new ReflectionClass($manager))->isUninitializedLazyObject($manager);
    }
}
