<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;
use ReflectionMethod;

if ((new ReflectionMethod(RepositoryFactory::class, 'getRepository'))->hasReturnType()) {
    // ORM >= 3
    /** @internal */
    trait RepositoryFactoryCompatibility
    {
        /**
         * Gets the repository for an entity class.
         *
         * @param class-string<T> $entityName
         *
         * @return EntityRepository<T>
         *
         * @template T of object
         */
        public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
        {
            return $this->doGetRepository($entityManager, $entityName, true);
        }
    }
} else {
    // ORM 2
    /** @internal */
    trait RepositoryFactoryCompatibility
    {
        /** {@inheritDoc} */
        public function getRepository(EntityManagerInterface $entityManager, $entityName): ObjectRepository
        {
            return $this->doGetRepository($entityManager, $entityName, false);
        }
    }
}
