<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Collection;

use Doctrine\Bundle\DoctrineBundle\Collection\ContainerDelegateCollectionFactory;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\Document;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\DocumentList;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\Folder;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\TenantContext;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCaseAllPublicCompilerPass;
use Doctrine\ORM\Collection\DelegateCollection;
use Doctrine\ORM\Collection\DelegateCollectionFactory;
use Doctrine\ORM\Exception\UninstantiableCollection;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

use function array_column;
use function interface_exists;
use function iterator_to_array;
use function md5;
use function set_exception_handler;
use function sprintf;
use function sys_get_temp_dir;

class ContainerDelegateCollectionFactoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(DelegateCollectionFactory::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM 3.9');
    }

    public function testCreateReturnsTheServiceOfTheLocator(): void
    {
        $collection = new DocumentList(new TenantContext());
        $factory    = new ContainerDelegateCollectionFactory(new ServiceLocator([
            DocumentList::class => static fn (): DocumentList => $collection,
        ]));

        $this->assertSame($collection, $factory->create(DocumentList::class));
    }

    public function testCreateTellsHowToCreateACollectionThatNeedsArguments(): void
    {
        $factory = new ContainerDelegateCollectionFactory(new ServiceLocator([]));

        try {
            $factory->create(DocumentList::class);
            self::fail('The collection needs a TenantContext, it cannot be created without arguments.');
        } catch (LogicException $e) {
            $this->assertSame(
                sprintf('The custom collection "%s" cannot be created without arguments. Either register it as a service, for instance by moving it out of a directory excluded from the service definitions such as "src/Entity/", or create it in a factory decorating the "doctrine.orm.container_delegate_collection_factory" service.', DocumentList::class),
                $e->getMessage(),
            );
            $this->assertInstanceOf(UninstantiableCollection::class, $e->getPrevious());
        }
    }

    public function testCreateHandsTheClassesThatAreNotServicesOverToTheDefaultFactory(): void
    {
        $collection = new DocumentList(new TenantContext());
        $default    = new class ($collection) implements DelegateCollectionFactory {
            public function __construct(private readonly DocumentList $collection)
            {
            }

            public function create(string $collectionClass): DelegateCollection
            {
                return $this->collection;
            }
        };

        $factory = new ContainerDelegateCollectionFactory(new ServiceLocator([]), $default);

        $this->assertSame($collection, $factory->create(DocumentList::class));
    }

    public function testLoadedEntitiesGetTheirOwnCollectionFromTheContainer(): void
    {
        $exceptionHandler = set_exception_handler(null);

        $kernel = new ContainerDelegateCollectionFactoryTestKernel();
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $em        = $container->get('doctrine.orm.default_entity_manager');
            $tenant    = $container->get(TenantContext::class);
            $this->assertInstanceOf(TenantContext::class, $tenant);

            (new SchemaTool($em))->createSchema([$em->getClassMetadata(Folder::class), $em->getClassMetadata(Document::class)]);

            $documents = [new Document(), new Document()];
            foreach ($documents as $document) {
                $folder = new Folder(new DocumentList($tenant));
                $folder->documents->add($document);
                $em->persist($folder);
            }

            $em->flush();
            $em->clear();

            [$first, $second] = $em->getRepository(Folder::class)->findBy([], ['id' => 'ASC']);

            $this->assertNotSame($first->documents, $second->documents);
            $this->assertSame($tenant, $first->documents->tenant);
            $this->assertSame($tenant, $second->documents->tenant);
            $this->assertSame([$documents[0]->id], array_column(iterator_to_array($first->documents), 'id'));
            $this->assertSame([$documents[1]->id], array_column(iterator_to_array($second->documents), 'id'));
        } finally {
            $kernel->shutdown();
            // The kernel registers Symfony's debug exception handler on boot but does
            // not restore it on shutdown, so restore it here to avoid a risky test.
            set_exception_handler($exceptionHandler);
        }
    }
}

class ContainerDelegateCollectionFactoryTestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    /** @return iterable<Bundle> */
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new DoctrineBundle()];
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
                    'memory' => true,
                ],
                'orm' => [
                    'mappings' => [
                        'Fixtures' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/Fixtures',
                            'prefix' => 'Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
            $container->register(TenantContext::class)
                ->setPublic(true);
            $container->register(DocumentList::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
            $container->register('logger', NullLogger::class);
            $container->getCompilerPassConfig()->addPass(new TestCaseAllPublicCompilerPass());
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/sf_kernel_' . md5(static::class);
    }
}
