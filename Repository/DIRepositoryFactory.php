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

namespace DoctrineBundle\Repository;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;

/**
 * Class RepositoryFactory
 *
 * @author Miguel Angel Garzón <magarzon@gmail.com>
 */
final class DIRepositoryFactory implements RepositoryFactory
{
    /**
     * The list of EntityRepository instances.
     *
     * @var \Doctrine\Common\Persistence\ObjectRepository[]
     */
    private $repositoryList = [];

    /**
     * @var array
     */
    private $repositoryDefinitions = [];

    /**
     * DIRepositoryFactory constructor.
     * @param array $repositoryDefinitions
     */
    public function __construct(array $repositoryDefinitions = [])
    {
        $this->repositoryDefinitions = $repositoryDefinitions;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        $repositoryHash = $entityManager->getClassMetadata($entityName)->getName().spl_object_hash($entityManager);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        $repository = $this->createRepository($entityManager, $entityName);

        $this->setterDependencies($repository, $entityName);

        return $this->repositoryList[$repositoryHash] = $repository;
    }

    /**
     * @param ObjectRepository $repository
     * @param $entityName
     */
    private function setterDependencies(ObjectRepository $repository, $entityName)
    {
        foreach ($this->repositoryDefinitions as $definition) {
            if ($definition['entity'] === $entityName) {
                $setters = $definition['setters'];
                foreach ($setters as $method) {
                    list($setter, $parameters) = $method;
                    call_user_func_array([$repository, $setter], $parameters);
                }
                break;
            }
        }
    }

    /**
     * Create a new repository instance for an entity class.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager The EntityManager instance.
     * @param string                               $entityName    The name of the entity.
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    private function createRepository(EntityManagerInterface $entityManager, $entityName)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadata */
        $metadata            = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return new $repositoryClassName($entityManager, $metadata);
    }
}
