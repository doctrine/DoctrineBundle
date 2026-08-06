<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Registers the tagged class as a Doctrine DBAL type on the connection.
 *
 * With DBAL >= 4.5 and ORM >= 3.7 when the ORM are used, the type is registered
 * per connection and enables constructor dependency injection.
 *
 * With DBAL < 4.5, the type is registered globally instead.
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
