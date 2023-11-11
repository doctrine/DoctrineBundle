<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function sprintf;

/**
 * @internal Extend {@see ServiceEntityRepository} instead.
 *
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class ServiceEntityRepositoryProxy extends EntityRepository implements ServiceEntityRepositoryInterface
{
    private ?EntityRepository $repository = null;

    /** @param class-string<T> $entityClass The class name of the entity this repository manages */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $entityClass,
    ) {
        if (! $this instanceof LazyObjectInterface) {
            return;
        }

        $this->repository = $this->resolveRepository();
    }

    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return ($this->repository ??= $this->resolveRepository())
            ->createQueryBuilder($alias, $indexBy);
    }

    public function createResultSetMappingBuilder(string $alias): ResultSetMappingBuilder
    {
        return ($this->repository ??= $this->resolveRepository())
            ->createResultSetMappingBuilder($alias);
    }

    public function find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null): object|null
    {
        return ($this->repository ??= $this->resolveRepository())
            ->find($id, $lockMode, $lockVersion);
    }

    /** {@inheritDoc} */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return ($this->repository ??= $this->resolveRepository())
            ->findBy($criteria, $orderBy, $limit, $offset);
    }

    /** {@inheritDoc} */
    public function findOneBy(array $criteria, ?array $orderBy = null): object|null
    {
        return ($this->repository ??= $this->resolveRepository())
            ->findOneBy($criteria, $orderBy);
    }

    /** {@inheritDoc} */
    public function count(array $criteria = []): int
    {
        return ($this->repository ??= $this->resolveRepository())->count($criteria);
    }

    /** {@inheritDoc} */
    public function __call(string $method, array $arguments): mixed
    {
        return ($this->repository ??= $this->resolveRepository())->$method(...$arguments);
    }

    protected function getEntityName(): string
    {
        return ($this->repository ??= $this->resolveRepository())->getEntityName();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return ($this->repository ??= $this->resolveRepository())->getEntityManager();
    }

    protected function getClassMetadata(): ClassMetadata
    {
        return ($this->repository ??= $this->resolveRepository())->getClassMetadata();
    }

    public function matching(Criteria $criteria): AbstractLazyCollection&Selectable
    {
        return ($this->repository ??= $this->resolveRepository())->matching($criteria);
    }

    private function resolveRepository(): EntityRepository
    {
        $manager = $this->registry->getManagerForClass($this->entityClass);

        if ($manager === null) {
            throw new LogicException(sprintf(
                'Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.',
                $this->entityClass,
            ));
        }

        return new EntityRepository($manager, $manager->getClassMetadata($this->entityClass));
    }
}
