<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\VarExporter\LazyGhostTrait;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function sprintf;

/**
 * @internal Extend {@see ServiceEntityRepository} instead.
 *
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class LazyServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    use LazyGhostTrait {
        createLazyGhost as private;
    }

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
                    $entityClass,
                ));
            }

            parent::__construct($manager, $manager->getClassMetadata($entityClass));

            return $this->$property;
        };

        if ($this instanceof LazyObjectInterface) {
            $initializer($this, '_entityName');

            return;
        }

        self::createLazyGhost([
            "\0*\0_em" => $initializer,
            "\0*\0_class" => $initializer,
            "\0*\0_entityName" => $initializer,
        ], null, $this);
    }
}
