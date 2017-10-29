<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;
use Psr\Container\ContainerInterface;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;

/**
 * A custom repository factory that fetches repositories from the container.
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
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $repositoryServiceId = $metadata->customRepositoryClassName;

        if (null !== $repositoryServiceId) {
            if (!$this->container->has($metadata->customRepositoryClassName)) {
                throw new \RuntimeException(sprintf('Could not find the repository service for the "%s" entity. Make sure the "%s" service exists and is tagged with "%s".', $entityName, $repositoryServiceId, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            $repository = $this->container->get($metadata->customRepositoryClassName);

            if (!$repository instanceof EntityRepository) {
                throw new \RuntimeException(sprintf('The service "%s" must extend EntityRepository (or a base class, like ServiceEntityRepository).', $repositoryServiceId));
            }

            return $repository;
        }

        $repositoryHash = $metadata->getName() . spl_object_hash($entityManager);
        if (isset($this->managedRepositories[$repositoryHash])) {
            return $this->managedRepositories[$repositoryHash];
        }

        $repositoryClassName = $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return $this->managedRepositories[$repositoryHash] = new $repositoryClassName($entityManager, $metadata);
    }
}
