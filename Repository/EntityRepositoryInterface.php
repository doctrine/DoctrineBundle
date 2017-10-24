<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * An interface that all entity repositories should implement.
 *
 * This contains all of the public methods found in Doctrine\ORM\EntityRepository
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface EntityRepositoryInterface extends ObjectRepository
{
    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     *
     * @param string $alias
     * @param string $indexBy The index for the from.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias, $indexBy = null);

    /**
     * Creates a new result set mapping builder for this entity.
     *
     * The column naming strategy is "INCREMENT".
     *
     * @param string $alias
     *
     * @return Query\ResultSetMappingBuilder
     */
    public function createResultSetMappingBuilder($alias);

    /**
     * Creates a new Query instance based on a predefined metadata named query.
     *
     * @param string $queryName
     *
     * @return Query
     */
    public function createNamedQuery($queryName);

    /**
     * Creates a native SQL query.
     *
     * @param string $queryName
     *
     * @return NativeQuery
     */
    public function createNativeNamedQuery($queryName);

    /**
     * Clears the repository, causing all managed entities to become detached.
     *
     * @return void
     */
    public function clear();

    /**
     * Select all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function matching(Criteria $criteria);
}
