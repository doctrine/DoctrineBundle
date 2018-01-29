<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerRepositoryFactoryTest extends TestCase
{
    public function testGetRepositoryReturnsService()
    {
        if (! interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $em        = $this->createEntityManager(['Foo\CoolEntity' => 'my_repo']);
        $repo      = new StubRepository($em, new ClassMetadata(''));
        $container = $this->createContainer(['my_repo' => $repo]);

        $factory = new ContainerRepositoryFactory($container);
        $this->assertSame($repo, $factory->getRepository($em, 'Foo\CoolEntity'));
    }

    public function testGetRepositoryReturnsEntityRepository()
    {
        if (! interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $container = $this->createContainer([]);
        $em        = $this->createEntityManager(['Foo\BoringEntity' => null]);

        $factory    = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($em, 'Foo\BoringEntity');
        $this->assertInstanceOf(EntityRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($em, 'Foo\BoringEntity'));
    }

    public function testCustomRepositoryIsReturned()
    {
        if (! interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The service "my_repo" must extend EntityRepository (or a base class, like ServiceEntityRepository).
     */
    public function testServiceRepositoriesMustExtendEntityRepository()
    {
        if (! interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $repo = new \stdClass();

        $container = $this->createContainer(['my_repo' => $repo]);

        $em = $this->createEntityManager(['Foo\CoolEntity' => 'my_repo']);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "Doctrine\Bundle\DoctrineBundle\Tests\Repository\StubServiceRepository" entity repository implements "Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface", but its service could not be found. Make sure the service exists and is tagged with "doctrine.repository_service".
     */
    public function testRepositoryMatchesServiceInterfaceButServiceNotFound()
    {
        if (! interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $container = $this->createContainer([]);

        $em = $this->createEntityManager([
            'Foo\CoolEntity' => StubServiceRepository::class,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "Foo\CoolEntity" entity has a repositoryClass set to "not_a_real_class", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "doctrine.repository_service".
     */
    public function testCustomRepositoryIsNotAValidClass()
    {
        if (interface_exists(ContainerInterface::class)) {
            $container = $this->createContainer([]);
        } else {
            // Symfony 3.2 and lower support
            $container = null;
        }

        $em = $this->createEntityManager(['Foo\CoolEntity' => 'not_a_real_class']);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    private function createContainer(array $services)
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->any())
            ->method('has')
            ->willReturnCallback(function ($id) use ($services) {
                return isset($services[$id]);
            });
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($services) {
                return $services[$id];
            });

        return $container;
    }

    private function createEntityManager(array $entityRepositoryClasses)
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
            ->willReturnCallback(function ($class) use ($classMetadatas) {
                return $classMetadatas[$class];
            });

        $em->expects($this->any())
            ->method('getConfiguration')
            ->willReturn(new Configuration());

        return $em;
    }
}

class StubRepository extends EntityRepository
{
}

class StubServiceRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
}
