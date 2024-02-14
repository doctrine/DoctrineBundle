<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;

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
class ServiceEntityRepositoryOrm2 extends LazyServiceEntityRepository
{
}
