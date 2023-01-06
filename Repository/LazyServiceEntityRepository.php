<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\VarExporter\LazyGhostTrait;

use function sprintf;

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
 * @internal to be renamed ServiceEntityRepository when PHP 8.1 / Symfony 6.2 becomes required
 *
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class LazyServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    use LazyGhostTrait;

    /**
     * @param string $entityClass The class name of the entity this repository manages
     * @psalm-param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        $initializer = function ($instance, $property) use ($registry, $entityClass) {
            $manager = $registry->getManagerForClass($entityClass);

            if ($manager === null) {
                throw new LogicException(sprintf(
                    'Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.',
                    $entityClass
                ));
            }

            parent::__construct($manager, $manager->getClassMetadata($entityClass));

            return $this->$property;
        };

        self::createLazyGhost([
            "\0*\0_em" => $initializer,
            "\0*\0_class" => $initializer,
            "\0*\0_entityName" => $initializer,
        ], null, $this);
    }
}
