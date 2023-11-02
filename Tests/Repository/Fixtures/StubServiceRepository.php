<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Repository\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\EntityRepository;

class StubServiceRepository extends EntityRepository implements ServiceEntityRepositoryInterface
{
}
