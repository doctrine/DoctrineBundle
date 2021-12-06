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
        public ?string $event = null,
        public ?string $method = null,
        public ?bool $lazy = null,
        public ?string $entityManager = null,
        public ?string $entity = null,
    ) {
    }
}
