<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Generator;
use IteratorAggregate;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function array_keys;

/**
 * Provides the types registered as services for a connection, and falls back to the global type
 * registry for every other name.
 *
 * A connection that has its own type provider does not read the global type registry anymore, so
 * the fallback keeps those types available, for example a type registered by another bundle or by
 * the application itself.
 *
 * Only the types of the connection take precedence: a registry also knows the built-in types, so
 * querying one first would hide a built-in type overridden with Type::overrideType().
 *
 * @internal
 *
 * @implements IteratorAggregate<string, Type>
 */
final class TypeProviderGlobalFallback implements TypeProvider, IteratorAggregate
{
    private static TypeProvider $globalFallback;

    /** @param ServiceProviderInterface<Type> $services */
    public function __construct(private readonly ServiceProviderInterface $services)
    {
    }

    public function get(string $name): Type
    {
        if ($this->services->has($name)) {
            return $this->services->get($name);
        }

        return $this->global()->get($name);
    }

    public function has(string $name): bool
    {
        return $this->services->has($name) || $this->global()->has($name);
    }

    /** @return Generator<string, Type> */
    public function getIterator(): Generator
    {
        foreach (array_keys($this->services->getProvidedServices()) as $name) {
            yield $name => $this->services->get($name);
        }

        foreach ($this->global() as $name => $type) {
            if ($this->services->has($name)) {
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
