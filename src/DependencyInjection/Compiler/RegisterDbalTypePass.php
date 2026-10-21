<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\Bundle\DoctrineBundle\Dbal\TypeProviderGlobalFallback;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function array_column;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function is_subclass_of;
use function iterator_to_array;
use function method_exists;
use function sprintf;
use function str_starts_with;

/** @internal */
final class RegisterDbalTypePass implements CompilerPassInterface
{
    private const string TAG = 'doctrine.dbal.type';

    /** @param ReflectionClass<*> $reflector */
    public static function autoconfigureFromAttribute(ChildDefinition $definition, AsDbalType $type, ReflectionClass $reflector): void
    {
        $definition->addTag(self::TAG, [
            'type_name' => $type->name ?? $reflector->name,
            'connection' => $type->connection,
        ]);
    }

    public function process(ContainerBuilder $container): void
    {
        // Per-connection type providers require DBAL >= 4.6.
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            $this->registerInTypeRegistry($container);
            // A new registry only holds the built-in types
            $builtinTypes = array_map(static fn (Type $type): string => $type::class, iterator_to_array(new TypeRegistry()));
        } else {
            $this->registerInConfig($container);
            // The global registry may also hold the types of code that ran earlier in this process
            $builtinTypes = array_filter(Type::getTypesMap(), static fn (string $class): bool => str_starts_with($class, 'Doctrine\\DBAL\\Types\\'));
        }

        $this->preloadTypes($container, $builtinTypes);
    }

    /**
     * DBAL creates the built-in types and the configured ones when they are first used: preload their classes.
     *
     * @param array<string, string> $builtinTypes
     */
    private function preloadTypes(ContainerBuilder $container, array $builtinTypes): void
    {
        if (! $container->hasDefinition('doctrine.dbal.connection_factory')) {
            return;
        }

        /** @var array<string, array{class: string}> $configTypes */
        $configTypes = $container->getParameter('doctrine.dbal.connection_factory.types');
        $factory     = $container->getDefinition('doctrine.dbal.connection_factory');

        foreach (array_merge(array_values($builtinTypes), array_column($configTypes, 'class')) as $class) {
            $factory->addTag('container.preload', ['class' => $class]);
        }
    }

    /**
     * New approach: inject a per-connection TypeRegistry with lazy service resolution (DBAL >= 4.6).
     */
    private function registerInTypeRegistry(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.connections')) {
            return;
        }

        // Config-based types (apply to all connections)
        /** @var array<string, array{class: string}> $configTypes */
        $configTypes = $container->getParameter('doctrine.dbal.connection_factory.types');

        $taggedServiceIds = $container->findTaggedServiceIds(self::TAG);

        if ($configTypes === [] && $taggedServiceIds === []) {
            return;
        }

        foreach (array_keys($container->getParameter('doctrine.connections')) as $name) {
            $services = [];

            // Config-based types become inline definitions in the ServiceLocator
            foreach ($configTypes as $typeName => $typeConfig) {
                $services[$typeName] = new Definition($typeConfig['class']);
            }

            // Service-tagged types: global (no connection restriction) or matching this connection
            foreach ($taggedServiceIds as $id => $tags) {
                foreach ($tags as $tag) {
                    if ($name !== ($tag['connection'] ?? $name)) {
                        continue;
                    }

                    $services[$tag['type_name'] ?? $id] = new Reference($id);
                }
            }

            $providerId = sprintf('doctrine.dbal.%s_connection.type_provider', $name);

            // Inject a ServiceLocator so types are resolved lazily on first use. The locator is
            // keyed by type name, and it is the only argument the TypeRegistry needs. The registry
            // is not a service on its own, it is inlined in the provider of the connection.
            $locatorRef = ServiceLocatorTagPass::register($container, $services);

            // The provider of the connection also resolves the types that are registered globally,
            // which the registry of the connection alone would hide.
            $container->setDefinition($providerId, new Definition(TypeProviderGlobalFallback::class, [
                new Definition(TypeRegistry::class, [$locatorRef]),
            ]));

            $container
                ->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))
                ->addMethodCall('setTypeProvider', [new Reference($providerId)]);
        }
    }

    /**
     * Fallback approach: register types in the global type registry via doctrine.dbal.connection_factory.types.
     * Used when DBAL does not support TypeRegistry injection (DBAL < 4.6).
     * Does not support DI or per-connection type restriction.
     */
    private function registerInConfig(ContainerBuilder $container): void
    {
        $types = $container->getParameter('doctrine.dbal.connection_factory.types');

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);

            $class = $definition->getClass();
            if (! $class) {
                throw new InvalidArgumentException(sprintf('The definition of "%s" must define its class.', $id));
            }

            if (! is_subclass_of($class, Type::class)) {
                throw new InvalidArgumentException(sprintf('The "%s" class must extends "%s".', $class, Type::class));
            }

            // The type is instantiated by DBAL, not used as a service, so exclude its
            // definition from the container.
            $definition->addTag('container.excluded', ['source' => sprintf('by tag "%s"', self::TAG)]);

            foreach ($tags as $tag) {
                $types[$tag['type_name'] ?? $id] = ['class' => $class];
            }
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }
}
