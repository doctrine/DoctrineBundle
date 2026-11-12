<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Service tag to autoconfigure event listeners.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsDoctrineListener
{
    /**
     * @param string|list<string>|null $before The listeners to run before, as service ids or classes; requires symfony/doctrine-bridge 8.2
     * @param string|list<string>|null $after  The listeners to run after, as service ids or classes; requires symfony/doctrine-bridge 8.2
     */
    public function __construct(
        public string $event,
        public int|null $priority = null,
        public string|null $connection = null,
        public string|array|null $before = null,
        public string|array|null $after = null,
    ) {
    }
}
