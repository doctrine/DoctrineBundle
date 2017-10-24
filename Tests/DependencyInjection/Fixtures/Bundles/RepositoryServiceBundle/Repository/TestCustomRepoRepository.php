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

namespace Fixtures\Bundles\RepositoryServiceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\AbstractServiceRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestCustomRepoEntity;

class TestCustomRepoRepository extends AbstractServiceRepository
{
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    protected function getEntityRepository()
    {
        return $this->registry->getManager();
    }

    public function getClassName()
    {
        return TestCustomRepoEntity::class;
    }
}
