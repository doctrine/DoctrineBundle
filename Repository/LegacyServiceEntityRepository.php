<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

use function sprintf;

/**
 * @internal Extend {@see ServiceEntityRepository} instead.
 *
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class LegacyServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    /**
     * @param string $entityClass The class name of the entity this repository manages
     * @psalm-param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        $manager = $registry->getManagerForClass($entityClass);

        if ($manager === null) {
            throw new LogicException(sprintf(
                'Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.',
                $entityClass,
            ));
        }

        parent::__construct($manager, $manager->getClassMetadata($entityClass));
    }
}
