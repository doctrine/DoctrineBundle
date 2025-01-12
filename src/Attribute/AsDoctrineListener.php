<?php

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Service tag to autoconfigure event listeners.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsDoctrineListener
{
    public function __construct(
        public string $event,
        public int|null $priority = null,
        public string|null $connection = null,
    ) {
    }
}
