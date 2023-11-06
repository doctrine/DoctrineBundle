<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class CustomIdGenerator extends AbstractIdGenerator
{
    /**
     * {@inheritDoc}
     */
    public function generate(EntityManager $em, $entity)
    {
        return 42;
    }
}
