<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class CustomIdGenerator extends AbstractIdGenerator
{
    /** @var int */
    public $value = 42;

    public function generate(EntityManager $em, $entity)
    {
        return $this->value;
    }

    public function theMethod(int $value): self
    {
        $clone        = clone $this;
        $clone->value = $value;

        return $clone;
    }
}
