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

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Psr\Container\ContainerInterface;

/**
 * Returns repositories that are registered in the container, or a default implementation.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class ContainerRepositoryFactory implements RepositoryFactory
{
    public $container;

    private $genericRepositories = array();

    /**
     * @var ContainerInterface|SymfonyContainerInterface $container
     */
    public function __construct(/* ContainerInterface */ $container)
    {
        /*
         * Compatibility layer for Symfony 3.2 and lower.
         * When DoctrineBundle requires 3.3 or higher, this can
         * be removed and the above type-hint added.
         */
        if (interface_exists(ContainerInterface::class)) {
            if (!$container instanceof ContainerInterface) {
                throw new \InvalidArgumentException(sprintf('Argument 1 passed to %s::__construct() must be an instance of "%s"', self::class, ContainerInterface::class));
            }
        } elseif (!$container instanceof SymfonyContainerInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 1 passed to %s::__construct() must be an instance of "%s"', self::class, SymfonyContainerInterface::class));
        }

        $this->container = $container;
    }

    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadata */
        $metadata = $entityManager->getClassMetadata($entityName);
        $entityClass = $metadata->name;
        $repositoryServiceId = $metadata->customRepositoryClassName;

        if (null !== $repositoryServiceId) {
            if (!$this->container->has($repositoryServiceId)) {
                throw new \RuntimeException(sprintf('Could not find the repository service for the "%s" entity. Make sure the "%s" service exists and is tagged with "%s"', $entityName, $repositoryServiceId, ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG));
            }

            return $this->container->get($repositoryServiceId);
        }

        $repositoryHash = $entityClass . spl_object_hash($entityManager);
        if (!isset($this->genericRepositories[$repositoryHash])) {
            $this->genericRepositories[$repositoryHash] = new DefaultServiceRepository($entityManager, $entityName);
        }

        return $this->genericRepositories[$repositoryHash];
    }
}
