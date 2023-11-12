<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\VarExporter\LazyGhostTrait;

use function property_exists;
use function trait_exists;

if (property_exists(EntityRepository::class, '_entityName')) {
    if (trait_exists(LazyGhostTrait::class)) {
        // ORM 2 with VarExporter
        /**
         * Optional EntityRepository base class with a simplified constructor (for autowiring).
         *
         * To use in your class, inject the "registry" service and call
         * the parent constructor. For example:
         *
         * class YourEntityRepository extends ServiceEntityRepository
         * {
         *     public function __construct(ManagerRegistry $registry)
         *     {
         *         parent::__construct($registry, YourEntity::class);
         *     }
         * }
         *
         * @template T of object
         * @template-extends LazyServiceEntityRepository<T>
         */
        class ServiceEntityRepository extends LazyServiceEntityRepository
        {
        }
    } else {
        // ORM 2 without VarExporter
        /**
         * Optional EntityRepository base class with a simplified constructor (for autowiring).
         *
         * To use in your class, inject the "registry" service and call
         * the parent constructor. For example:
         *
         * class YourEntityRepository extends ServiceEntityRepository
         * {
         *     public function __construct(ManagerRegistry $registry)
         *     {
         *         parent::__construct($registry, YourEntity::class);
         *     }
         * }
         *
         * @template T of object
         * @template-extends LegacyServiceEntityRepository<T>
         */
        class ServiceEntityRepository extends LegacyServiceEntityRepository
        {
        }
    }
} else {
    // ORM 3
    /**
     * Optional EntityRepository base class with a simplified constructor (for autowiring).
     *
     * To use in your class, inject the "registry" service and call
     * the parent constructor. For example:
     *
     * class YourEntityRepository extends ServiceEntityRepository
     * {
     *     public function __construct(ManagerRegistry $registry)
     *     {
     *         parent::__construct($registry, YourEntity::class);
     *     }
     * }
     *
     * @template T of object
     * @template-extends ServiceEntityRepositoryProxy<T>
     */
    class ServiceEntityRepository extends ServiceEntityRepositoryProxy
    {
    }
}
