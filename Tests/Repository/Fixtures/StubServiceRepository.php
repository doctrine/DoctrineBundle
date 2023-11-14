<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @template T of object
 * @extends EntityRepository<T>
 */
class StubServiceRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
}
