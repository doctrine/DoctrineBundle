<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerRepositoryFactoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists(ContainerInterface::class)) {
            $this->markTestSkipped('The Psr\Container\ContainerInterface (supplied by Symfony 3.3) is needed for this feature.');
        }
    }

    public function testGetRepositoryReturnsService()
    {
        $em = $this->createEntityManager(array(
            'Foo\CoolEntity' => 'my_repo'
        ));
        $repo = new StubRepository($em, new ClassMetadata(''));
        $container = $this->createContainer(array(
            'my_repo' => $repo
        ));

        $factory = new ContainerRepositoryFactory($container);
        $this->assertSame($repo, $factory->getRepository($em, 'Foo\CoolEntity'));
    }

    public function testGetRepositoryReturnsEntityRepository()
    {
        $container = $this->createContainer(array());
        $em = $this->createEntityManager(array(
            'Foo\BoringEntity' => null
        ));

        $factory = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($em, 'Foo\BoringEntity');
        $this->assertInstanceOf(EntityRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($em, 'Foo\BoringEntity'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The service "my_repo" must extend EntityRepository (or a base class, like ServiceEntityRepository).
     */
    public function testServiceRepositoriesMustExtendEntityRepository()
    {
        $repo = new \stdClass();
        $container = $this->createContainer(array(
            'my_repo' => $repo
        ));
        $em = $this->createEntityManager(array(
            'Foo\CoolEntity' => 'my_repo'
        ));

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not find the repository service for the "Foo\CoolEntity" entity. Make sure the "my_repo" service exists and is tagged with "doctrine.repository_service".
     */
    public function testIfServiceDoesNotExist()
    {
        $container = $this->createContainer(array());
        $em = $this->createEntityManager(array(
            'Foo\CoolEntity' => 'my_repo'
        ));

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($em, 'Foo\CoolEntity');
    }

    private function createContainer(array $services)
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->any())
            ->method('has')
            ->willReturnCallback(function($id) use ($services) {
                return isset($services[$id]);
            });
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function($id) use ($services) {
                return $services[$id];
            });

        return $container;
    }

    private function createEntityManager(array $entityRepositoryClasses)
    {
        $classMetadatas = array();
        foreach ($entityRepositoryClasses as $entityClass => $entityRepositoryClass) {
            $metadata = new ClassMetadata($entityClass);
            $metadata->customRepositoryClassName = $entityRepositoryClass;

            $classMetadatas[$entityClass] = $metadata;
        }

        $em = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $em->expects($this->any())
            ->method('getClassMetadata')
            ->willReturnCallback(function($class) use ($classMetadatas) {
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
