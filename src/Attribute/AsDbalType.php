<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Registers the class as a Doctrine DBAL type.
 *
 * With DBAL >= 4.6, the type is registered per connection and created by the
 * dependency injection container, so its constructor can receive services.
 *
 * With DBAL < 4.6, the type is registered in the global registry instead,
 * without dependency injection and without per-connection restriction.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsDbalType
{
    /**
     * @param string|null $name       The DBAL type name used in column mappings (e.g. in #[Column(type: ...)]).
     *                                Defaults to the fully-qualified class name of the type when omitted.
     * @param string|null $connection Restrict the type to a specific named connection.
     *                                When null (default), the type is registered for all connections.
     */
    public function __construct(
        public string|null $name = null,
        public string|null $connection = null,
    ) {
    }
}
