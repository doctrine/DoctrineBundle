<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class CustomIdGenerator extends AbstractIdGenerator
{
    /**
     * {@inheritDoc}
     */
    public function generateId(EntityManagerInterface $em, $entity): int
    {
        return 42;
    }
}
