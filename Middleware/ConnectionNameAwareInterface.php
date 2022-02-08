<?php

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

interface ConnectionNameAwareInterface
{
    public function setConnectionName(string $name): void;
}
