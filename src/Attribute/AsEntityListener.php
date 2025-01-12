<?php

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Service tag to autoconfigure entity listeners.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsEntityListener
{
    public function __construct(
        public string|null $event = null,
        public string|null $method = null,
        public bool|null $lazy = null,
        public string|null $entityManager = null,
        public string|null $entity = null,
        public int|null $priority = null,
    ) {
    }
}
