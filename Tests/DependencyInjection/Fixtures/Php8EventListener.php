<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEventListener;
use Doctrine\ORM\Events;

#[AsEventListener(Events::postFlush)]
final class Php8EventListener
{
    public function postFlush(): void
    {
    }
}
