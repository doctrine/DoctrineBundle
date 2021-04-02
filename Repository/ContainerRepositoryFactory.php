<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function class_exists;
use function is_a;
use function spl_object_hash;
use function sprintf;

/**
 * Fetches repositories from the container or falls back to normal creation.
 */
final class ContainerRepositoryFactory implements RepositoryFactory
{
    /** @var ObjectRepository[] */
    private $managedRepositories = [];

    /** @var ContainerInterface */
    private $container;

    /**
     * @param ContainerInterface $container A service locator containing the repositories
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName): ObjectRepository
    {
        $metadata            = $entityManager->getClassMetadata($entityName);
        $repositoryServiceId = $metadata->customRepositoryClassName;

        $customRepositoryName = $metadata->customRepositoryClassName;
        if ($customRepositoryName !== null) {
            // fetch from the container
            if ($this->container->has($customRepositoryName)) {
                $repository = $this->container->get($customRepositoryName);

                if (! $repository instanceof ObjectRepository) {
                    throw new RuntimeException(sprintf('The service "%s" must implement ObjectRepository (or extend a base class, like ServiceEntityRepository).', $repositoryServiceId));
                }

                return $repository;
            }

            // if not in the container but the class/id implements the interface, throw an error
            if (is_a($customRepositoryName, ServiceEntityRepositoryInterface::class, true)) {
                throw new RuntimeException(sprintf('The "%s" entity repository implements "%s", but its service could not be found. Make sure the service exists and is tagged with "%s".', $customRepositoryName, ServiceEntityRepositoryInterface::class, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            if (! class_exists($customRepositoryName)) {
                throw new RuntimeException(sprintf('The "%s" entity has a repositoryClass set to "%s", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "%s".', $metadata->name, $customRepositoryName, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            // allow the repository to be created below
        }

        return $this->getOrCreateRepository($entityManager, $metadata);
    }

    private function getOrCreateRepository(
        EntityManagerInterface $entityManager,
        ClassMetadata $metadata
    ): ObjectRepository {
        $repositoryHash = $metadata->getName() . spl_object_hash($entityManager);
        if (isset($this->managedRepositories[$repositoryHash])) {
            return $this->managedRepositories[$repositoryHash];
        }

        $repositoryClassName = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return $this->managedRepositories[$repositoryHash] = new $repositoryClassName($entityManager, $metadata);
    }
}
