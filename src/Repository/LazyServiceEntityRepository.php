<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function debug_backtrace;
use function sprintf;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @internal Extend {@see ServiceEntityRepository} instead.
 *
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class LazyServiceEntityRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
    /**
     * @param string $entityClass The class name of the entity this repository manages
     * @phpstan-param class-string<T> $entityClass
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $entityClass,
    ) {
        if ($this instanceof LazyObjectInterface) {
            $this->initialize();

            return;
        }

        unset($this->_em);
        unset($this->_class);
        unset($this->_entityName);
    }

    /** @return mixed */
    public function __get(string $name)
    {
        $this->initialize();

        $scope = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;

        return (fn (): mixed => $this->$name)->bindTo($this, $scope)();
    }

    public function __isset(string $name): bool
    {
        $this->initialize();

        $scope = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;

        return (fn (): bool => isset($this->$name))->bindTo($this, $scope)();
    }

    private function initialize(): void
    {
        $manager = $this->registry->getManagerForClass($this->entityClass);

        if (! $manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf(
                'Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.',
                $this->entityClass,
            ));
        }

        parent::__construct($manager, $manager->getClassMetadata($this->entityClass));
    }
}
