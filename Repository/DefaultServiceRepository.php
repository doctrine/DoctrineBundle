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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Default repository that's used for service repositories.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class DefaultServiceRepository implements EntityRepositoryInterface
{
    use RepositoryTrait;

    private $entityManager;

    private $className;

    public function __construct(EntityManagerInterface $entityManager, $className)
    {
        $this->entityManager = $entityManager;
        $this->className = $className;
    }

    /**
     * @return EntityRepository
     */
    private function getEntityRepository()
    {
        return $this->entityManager->getRepository($this->getClassName());
    }
}
