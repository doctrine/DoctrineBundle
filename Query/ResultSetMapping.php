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

namespace Doctrine\Bundle\DoctrineBundle\Query;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping as BaseMapping;

/**
 * A ResultSetMapping describes how a result set of an SQL query maps to a
 * Doctrine result.
 *
 * This ResultSetMapping objects allows the usage of namespace shortcuts.
 *
 * @author Jelmer Snoeck <jelmer@siphoc.com>
 */
class ResultSetMapping extends BaseMapping
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * Initiate the ResultSetMapping with the EntityManager.
     *
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Retrieve the Configuration we've passed through via the EntityManager.
     *
     * @return \Doctrine\ORM\Configuration
     */
    public function getConfig()
    {
        return $this->em->getConfiguration();
    }

    /**
     * Adds an entity result to this ResultSetMapping.
     *
     * @param string $class The class name or namespace shortcut of the entity.
     * @param string $alias The alias for the class. The alias must be unique among all entity
     *                                 results or joined entity results within this ResultSetMapping.
     * @param string|null $resultAlias The result alias with which the entity result should be
     *                                 placed in the result structure.
     *
     * @return ResultSetMapping This ResultSetMapping instance.
     */
    public function addEntityResult($class, $alias, $resultAlias = null)
    {
        $class = $this->getMetaClassName($class);

        return parent::addEntityResult($class, $alias, $resultAlias);
    }

    /**
     * Adds a joined entity result.
     *
     * @param string $class       The class name or namespace shortcut of the joined entity.
     * @param string $alias       The unique alias to use for the joined entity.
     * @param string $parentAlias The alias of the entity result that is the parent of this joined result.
     * @param object $relation    The association field that connects the parent entity result
     *                            with the joined entity result.
     *
     * @return ResultSetMapping This ResultSetMapping instance.
     */
    public function addJoinedEntityResult($class, $alias, $parentAlias, $relation)
    {
        $class = $this->getMetaClassName($class);

        return parent::addJoinedEntityResult($class, $alias, $parentAlias, $relation);
    }

    /**
     * Get the proper class name for a given shortcut or class name. This uses
     * the ClassMetaDataFactory to fetch the correct data.
     *
     * @param string    The class name or namespace shortcut of the entity.
     * @return string
     */
    private function getMetaClassName($class)
    {
        return $this->getConfig()->getClassMetadataFactoryName()
            ->getMetadataFor($class)->name;
    }
}
