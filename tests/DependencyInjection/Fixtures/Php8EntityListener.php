<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener()]
final class Php8EntityListener
{
    public function __invoke(): void
    {
    }

    public function postPersist(): void
    {
    }
}
