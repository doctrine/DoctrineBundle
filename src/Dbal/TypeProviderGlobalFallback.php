<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Generator;
use IteratorAggregate;

/**
 * Provides the types of the provider of a connection, and falls back to the global type registry
 * for the types that other code registers with Type::addType().
 *
 * A connection that has its own type provider does not read the global type registry anymore, so
 * the fallback keeps those types available, for example a type registered by another bundle or by
 * the application itself.
 *
 * @internal
 *
 * @implements IteratorAggregate<string, Type>
 */
final class TypeProviderGlobalFallback implements TypeProvider, IteratorAggregate
{
    private static TypeProvider $globalFallback;

    public function __construct(private readonly TypeProvider $provider)
    {
    }

    public function get(string $name): Type
    {
        if ($this->provider->has($name)) {
            return $this->provider->get($name);
        }

        return $this->global()->get($name);
    }

    public function has(string $name): bool
    {
        return $this->provider->has($name) || $this->global()->has($name);
    }

    /** @return Generator<string, Type> */
    public function getIterator(): Generator
    {
        $seen = [];
        foreach ($this->provider as $name => $type) {
            $seen[$name] = true;

            yield $name => $type;
        }

        foreach ($this->global() as $name => $type) {
            if ($seen[$name] ?? false) {
                continue;
            }

            yield $name => $type;
        }
    }

    /**
     * Returns the provider of the global type registry.
     *
     * The default provider of a DBAL Configuration is the global type registry, which is the
     * instance that Type::addType() and Type::getType() use. Reading it this way does not trigger
     * the deprecation of Type::getTypeRegistry().
     */
    private function global(): TypeProvider
    {
        return self::$globalFallback ??= (new Configuration())->getTypeProvider();
    }
}
