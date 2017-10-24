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

/**
 * Abstract helper class for creating custom service repository classes.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class AbstractServiceRepository implements EntityRepositoryInterface
{
    use RepositoryTrait;
}
