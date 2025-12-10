<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomClassRepoEntity;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Container;

use function assert;
use function interface_exists;
use function restore_exception_handler;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultManagerName());
    }

    public function testGetDefaultConnection(): void
    {
        $conn      = $this->createStub(Connection::class);
        $container = new Container();
        $container->set('doctrine.dbal.default_connection', $conn);

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection(): void
    {
        $conn      = $this->createStub(Connection::class);
        $container = new Container();
        $container->set('doctrine.dbal.default_connection', $conn);

        $registry = new Registry($container, ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    public function testGetUnknownConnection(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine ORM Connection named "default" does not exist.');
        $registry->getConnection('default');
    }

    public function testGetConnectionNames(): void
    {
        $registry = new Registry(new Container(), ['default' => 'doctrine.dbal.default_connection'], [], 'default', 'default');

        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager(): void
    {
        $em        = $this->createStub(ObjectManager::class);
        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $em);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager());
    }

    public function testGetEntityManager(): void
    {
        $em        = $this->createStub(ObjectManager::class);
        $container = new Container();
        $container->set('doctrine.orm.default_entity_manager', $em);

        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');

        $this->assertSame($em, $registry->getManager('default'));
    }

    public function testGetUnknownEntityManager(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.',
        );
        $registry->getManager('default');
    }

    public function testResetUnknownEntityManager(): void
    {
        $registry = new Registry(new Container(), [], [], 'default', 'default');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Doctrine ORM Manager named "default" does not exist.',
        );
        $registry->resetManager('default');
    }

    public function testIdentityMapsStayConsistentAfterReset(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new TestKernel();
        $kernel->boot();

        $container     = $kernel->getContainer();
        $registry      = $container->get('doctrine');
        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $repository = $entityManager->getRepository(TestCustomClassRepoEntity::class);
        assert($repository instanceof TestCustomClassRepoRepository);

        $entity = new TestCustomClassRepoEntity();
        $repository->getEntityManager()->persist($entity);

        $this->assertTrue($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertTrue($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $registry->reset();

        $this->assertFalse($entityManager->getUnitOfWork()->isEntityScheduled($entity));
        $this->assertFalse($repository->getEntityManager()->getUnitOfWork()->isEntityScheduled($entity));

        $entityManager->flush();

        restore_exception_handler();
    }
}
