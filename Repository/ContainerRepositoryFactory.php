<?php


namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use Psr\Container\ContainerInterface;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;

/**
 * Fetches repositories from the container or falls back to normal creation.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class ContainerRepositoryFactory implements RepositoryFactory
{
    private $managedRepositories = [];

    private $container;

    /**
     * @param ContainerInterface $container A service locator containing the repositories
     */
    public function __construct(ContainerInterface $container = null)
    {
        // When DoctrineBundle requires Symfony 3.3+, this can be removed
        // and the $container argument can become required.
        if (null === $container && class_exists(ServiceLocatorTagPass::class)) {
            throw new \InvalidArgumentException(sprintf('The first argument of %s::__construct() is required on Symfony 3.3 or higher.', self::class));
        }

        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $repositoryServiceId = $metadata->customRepositoryClassName;

        $customRepositoryName = $metadata->customRepositoryClassName;
        if (null !== $customRepositoryName) {
            // fetch from the container
            if ($this->container && $this->container->has($customRepositoryName)) {
                $repository = $this->container->get($customRepositoryName);

                if (!$repository instanceof EntityRepository) {
                    throw new \RuntimeException(sprintf('The service "%s" must extend EntityRepository (or a base class, like ServiceEntityRepository).', $repositoryServiceId));
                }

                return $repository;
            }

            // if not in the container but the class/id implements the interface, throw an error
            if (is_a($customRepositoryName, ServiceEntityRepositoryInterface::class, true)) {
                // can be removed when DoctrineBundle requires Symfony 3.3
                if (null === $this->container) {
                    throw new \RuntimeException(sprintf('Support for loading entities from the service container only works for Symfony 3.3 or higher. Upgrade your version of Symfony or make sure your "%s" class does not implement "%s"', $customRepositoryName, ServiceEntityRepositoryInterface::class));
                }

                throw new \RuntimeException(sprintf('The "%s" entity repository implements "%s", but its service could not be found. Make sure the service exists and is tagged with "%s".', $customRepositoryName, ServiceEntityRepositoryInterface::class, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            if (!class_exists($customRepositoryName)) {
                throw new \RuntimeException(sprintf('The "%s" entity has a repositoryClass set to "%s", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "%s".', $metadata->name, $customRepositoryName, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            // allow the repository to be created below
        }

        return $this->getOrCreateRepository($entityManager, $metadata);
    }

    private function getOrCreateRepository(EntityManagerInterface $entityManager, ClassMetadata $metadata)
    {
        $repositoryHash = $metadata->getName().spl_object_hash($entityManager);
        if (isset($this->managedRepositories[$repositoryHash])) {
            return $this->managedRepositories[$repositoryHash];
        }

        $repositoryClassName = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return $this->managedRepositories[$repositoryHash] = new $repositoryClassName($entityManager, $metadata);
    }
}
