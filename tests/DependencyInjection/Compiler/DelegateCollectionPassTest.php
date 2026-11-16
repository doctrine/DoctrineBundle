<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Collection\ContainerDelegateCollectionFactory;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DelegateCollectionPass;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\DocumentList;
use Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures\TenantContext;
use Doctrine\ORM\Collection\DelegateCollectionFactory;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use function interface_exists;

class DelegateCollectionPassTest extends TestCase
{
    public function testTaggedServicesAreIndexedByClassAndNotShared(): void
    {
        if (! interface_exists(DelegateCollectionFactory::class)) {
            self::markTestSkipped('This test requires ORM 3.9');
        }

        $container = new ContainerBuilder();
        $container->addCompilerPass(new DelegateCollectionPass());
        $container->register('doctrine.orm.container_delegate_collection_factory', ContainerDelegateCollectionFactory::class)
            ->setPublic(true)
            ->addArgument(null);
        $container->register('app.document_list', DocumentList::class)
            ->addArgument(new Definition(TenantContext::class))
            ->addTag('doctrine.orm.delegate_collection');

        $container->compile();

        $factory = $container->get('doctrine.orm.container_delegate_collection_factory');
        self::assertInstanceOf(ContainerDelegateCollectionFactory::class, $factory);

        $collection = $factory->create(DocumentList::class);
        self::assertInstanceOf(DocumentList::class, $collection);
        self::assertInstanceOf(TenantContext::class, $collection->tenant);
        self::assertNotSame($collection, $factory->create(DocumentList::class));
    }

    public function testServiceWithoutClassIsRejected(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.container_delegate_collection_factory', stdClass::class)
            ->addArgument(null);
        $container->register('app.document_list')
            ->addTag('doctrine.orm.delegate_collection');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class of the "app.document_list" service, tagged "doctrine.orm.delegate_collection", cannot be found.');

        (new DelegateCollectionPass())->process($container);
    }

    public function testTaggedServicesAreLeftAloneWithoutTheFactory(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.document_list', stdClass::class)
            ->addTag('doctrine.orm.delegate_collection');

        (new DelegateCollectionPass())->process($container);

        self::assertTrue($container->getDefinition('app.document_list')->isShared());
    }
}
