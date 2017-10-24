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

/**
 * A helpful trait when creating your own repository.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
trait RepositoryTrait
{
    /**
     * {@inheritDoc}
     */
    public function createQueryBuilder($alias, $indexBy = null)
    {
        return $this->getEntityRepository()->createQueryBuilder($alias, $indexBy);
    }

    /**
     * {@inheritDoc}
     */
    public function createResultSetMappingBuilder($alias)
    {
        return $this->getEntityRepository()->createResultSetMappingBuilder($alias);
    }

    /**
     * {@inheritDoc}
     */
    public function createNamedQuery($queryName)
    {
        return $this->getEntityRepository()->createNamedQuery($queryName);
    }

    /**
     * {@inheritDoc}
     */
    public function createNativeNamedQuery($queryName)
    {
        return $this->getEntityRepository()->createNativeNamedQuery($queryName);
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->getEntityRepository()->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria)
    {
        return $this->getEntityRepository()->matching($criteria);
    }

    /**
     * {@inheritDoc}
     */
    public function find($id)
    {
        return $this->getEntityRepository()->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll()
    {
        return $this->getEntityRepository()->findAll();
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->getEntityRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria)
    {
        return $this->getEntityRepository()->findOneBy($criteria);
    }
}
