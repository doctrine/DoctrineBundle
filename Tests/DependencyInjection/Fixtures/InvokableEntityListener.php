<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

final class InvokableEntityListener
{
    public function __invoke(): void
    {
    }

    public function postPersist(): void
    {
    }
}
