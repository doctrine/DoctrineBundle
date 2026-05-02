<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Registers a DBAL type as a service, with full dependency injection support
 * when DBAL's TypeRegistry injection is available (DBAL >= 4.5).
 *
 * When TypeRegistry injection is supported, the type is registered in the
 * per-connection TypeRegistry, enabling constructor dependency injection.
 * When not supported, the type falls back to the global type registry
 * via doctrine.dbal.connection_factory.types (no DI support).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsDbalType
{
    /**
     * @param string|null $name       The DBAL type name used in column mappings (e.g. in #[Column(type: ...)]).
     *                                Defaults to the fully-qualified class name of the type when omitted,
     *                                so that #[Column(type: MyType::class)] works without declaring a name.
     * @param string|null $connection Restrict the type to a specific named connection.
     *                                When null (default), the type is registered for all connections.
     *                                Ignored when TypeRegistry injection is not supported by the installed DBAL version.
     */
    public function __construct(
        public string|null $name = null,
        public string|null $connection = null,
    ) {
    }
}
