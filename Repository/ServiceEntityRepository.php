<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;

/**
 * Optional EntityRepository base class with a simplified constructor (for autowiring).
 *
 * To use in your class, inject the "registry" service and call
 * the parent constructor. For example:
 *
 * class YourEntityRepository extends ServiceEntityRepository
 * {
 *     public function __construct(RegistryInterface $registry)
 *     {
 *         parent::__construct($registry, YourEntity::class);
 *     }
 * }
 */
class ServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    /**
     * @param string $entityClass The class name of the entity this repository manages
     */
    public function __construct(ManagerRegistry $registry, $entityClass)
    {
        $manager = $registry->getManagerForClass($entityClass);

        parent::__construct($manager, $manager->getClassMetadata($entityClass));
    }
}
