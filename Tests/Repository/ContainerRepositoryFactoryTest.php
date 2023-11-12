<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures\StubRepository;
use Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures\StubServiceRepository;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\Container;

use function interface_exists;

class ContainerRepositoryFactoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testGetRepositoryReturnsService(): void
    {
        $em        = $this->createEntityManager(['Foo\CoolEntity' => 'my_repo']);
        $repo      = new StubRepository($em, new ClassMetadata('Foo\CoolEntity'));
        $container = $this->createContainer(['my_repo' => $repo]);

        $factory = new ContainerRepositoryFactory($container);
        $this->assertSame($repo, $factory->getRepository($em, 'Foo\CoolEntity'));
    }

    public function testGetRepositoryReturnsEntityRepository(): void
    {
        $container = $this->createContainer([]);
        $em        = $this->createEntityManager(['Foo\BoringEntity' => null]);

        $factory    = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($em, 'Foo\BoringEntity');
        $this->assertInstanceOf(EntityRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($em, 'Foo\BoringEntity'));
    }

    public function testCustomRepositoryIsReturned(): void
    {
        $container = $this->createContainer([]);
        $em        = $this->createEntityManager([
            'Foo\CustomNormalRepoEntity' => StubRepository::class,
        ]);

        $factory    = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($em, 'Foo\CustomNormalRepoEntity');
        $this->assertInstanceOf(StubRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($em, 'Foo\CustomNormalRepoEntity'));
    }

    public function testServiceRepositoriesMustExtendObjectRepository(): void
    {
        $repo = new stdClass();

        $container = $this->createContainer(['my_repo' => $repo]);

        $em = $this->createEntityManager(['Foo\CoolEntity' => 'my_repo']);

        $factory = new ContainerRepositoryFactory($container);
        $this->expectException(RuntimeException::class);
        if ((new ReflectionMethod(RepositoryFactory::class, 'getRepository'))->hasReturnType()) {
            $this->expectExceptionMessage('The service "my_repo" must extend EntityRepository (e.g. by extending ServiceEntityRepository), "stdClass" given.');
        } else {
            $this->expectExceptionMessage('The service "my_repo" must implement ObjectRepository (or extend a base class, like ServiceEntityRepository), "stdClass" given.');
        }

        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    public function testServiceRepositoriesCanNotExtendsEntityRepository(): void
    {
        $repo = $this->createStub(EntityRepository::class);

        $container = $this->createContainer(['my_repo' => $repo]);

        $em = $this->createEntityManager(['Foo\CoolEntity' => 'my_repo']);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
        $actualRepo = $factory->getRepository($em, 'Foo\CoolEntity');
        $this->assertSame($repo, $actualRepo);
    }

    public function testRepositoryMatchesServiceInterfaceButServiceNotFound(): void
    {
        $container = $this->createContainer([]);

        $em = $this->createEntityManager([
            'Foo\CoolEntity' => StubServiceRepository::class,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'EXCEPTION'
The "Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures\StubServiceRepository" entity repository implements "Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface", but its service could not be found. Make sure the service exists and is tagged with "doctrine.repository_service".
EXCEPTION);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    public function testCustomRepositoryIsNotAValidClass(): void
    {
        $container = $this->createContainer([]);

        $em = $this->createEntityManager(['Foo\CoolEntity' => 'not_a_real_class']);

        $factory = new ContainerRepositoryFactory($container);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'EXCEPTION'
The "Foo\CoolEntity" entity has a repositoryClass set to "not_a_real_class", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "doctrine.repository_service".
EXCEPTION);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    /** @param array<string, object> $services */
    private function createContainer(array $services): Container
    {
        $container = new Container();

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    /** @param array<class-string, ?string> $entityRepositoryClasses */
    private function createEntityManager(array $entityRepositoryClasses): EntityManagerInterface
    {
        $classMetadatas = [];
        foreach ($entityRepositoryClasses as $entityClass => $entityRepositoryClass) {
            $metadata                            = new ClassMetadata($entityClass);
            $metadata->customRepositoryClassName = $entityRepositoryClass;

            $classMetadatas[$entityClass] = $metadata;
        }

        $em = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $em->expects($this->any())
            ->method('getClassMetadata')
            ->willReturnCallback(static function ($class) use ($classMetadatas) {
                return $classMetadatas[$class];
            });

        $em->expects($this->any())
            ->method('getConfiguration')
            ->willReturn(new Configuration());

        return $em;
    }
}
