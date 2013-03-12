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
}
