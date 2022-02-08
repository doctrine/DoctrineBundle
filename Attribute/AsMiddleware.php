<?php

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsMiddleware
{
    /** @param string[] $connections */
    public function __construct(
        public array $connections = [],
    ) {
    }
}
