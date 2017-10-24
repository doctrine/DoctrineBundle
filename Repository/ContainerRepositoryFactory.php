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
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Returns repositories that are registered in the container, or a default implementation.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class ContainerRepositoryFactory implements RepositoryFactory
{
    public $container;

    private $genericRepositories = array();

    public function __construct(ContainerInterface $container)
    {
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
