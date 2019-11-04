<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use LogicException;

/**
 * Optional EntityRepository base class with a simplified constructor (for autowiring).
 *
 * To use in your class, inject the "registry" service and call
 * the parent constructor. For example:
 *
 * class YourEntityRepository extends ServiceEntityRepository
 * {
 *     public function __construct(RegistryInterface $registry, ...$args)
 *     {
 *         parent::__construct($registry, YourEntity::class, func_get_args());
 *     }
 * }
 */
class ServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    /**
     * All the arguments used by the constructor of the user-defined service repository
     *
     * @var mixed[]
     */
    private $args;

    /** @var Registry */
    private $registry;

    /** @var EntityManager */
    private $manager;

    /** @var string[] */
    private static $nonDefaultUserRepositoriesIds = [];

    /**
     * @param string $entityClass The class name of the entity this repository manages
     */
    public function __construct(ManagerRegistry $registry, string $entityClass, ...$args)
    {
        $userSpecifiedManager = null;

        // BC
        if (isset($args[0])) {
            $this->args = $args[0];

            // Is there a specific entity manager to use here? To be searched in the extra args we internally manage.
            foreach ($this->args as $arg) {
                if ($arg instanceof EntityManager) {
                    $userSpecifiedManager = $arg;
                    break;
                }
            }
        }

        // Default manager: "first one defined for the entity"
        $manager = $userSpecifiedManager ? : $registry->getManagerForClass($entityClass);

        if ($manager === null) {
            throw new LogicException(sprintf(
                'Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.',
                $entityClass
            ));
        }

        $this->registry = $registry;
        $this->manager  = $manager;

        parent::__construct($manager, $manager->getClassMetadata($entityClass));
    }

    /**
     * @param string|EntityManager $entityManagerRef
     *
     * @return self
     */
    public function withManager($entityManagerRef)
    {
        if (! $this->supportsMultipleManagers()) {
            // BC: Fails silently with the old behavior
            return $this;
        }

        // getFrom: instance
        if ($entityManagerRef instanceof EntityManager) {
            $userSpecifiedManager = $entityManagerRef;
        } else { // getFrom: name
            $userSpecifiedManager = $this->registry->getManager($entityManagerRef);
        }

        // If a different manager than the autowired-one is required, instantiate a new user's service-repository with the right one.
        if ($userSpecifiedManager !== $this->manager) {
            $managerInstanceId = static::class . ':' . spl_object_hash($userSpecifiedManager);

            if (! isset(self::$nonDefaultUserRepositoriesIds[$managerInstanceId])) {
                // Use the very-same arguments for the new instance but the manager name is added to be used as the default manager.
                $args   = $this->args;
                $args[] = $userSpecifiedManager;

                self::$nonDefaultUserRepositoriesIds[$managerInstanceId] = new static(...$args);
            }

            return self::$nonDefaultUserRepositoriesIds[$managerInstanceId];
        }

        return $this;
    }

    public function supportsMultipleManagers() : bool
    {
        return $this->args !== null;
    }
}
