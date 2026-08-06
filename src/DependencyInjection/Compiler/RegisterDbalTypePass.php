<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
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

use function array_combine;
use function array_keys;
use function is_subclass_of;
use function method_exists;
use function sprintf;

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
        if (method_exists(DbalConfiguration::class, 'setTypeProvider')) {
            $this->registerInTypeRegistry($container);
        } else {
            $this->registerInConfig($container);
        }
    }

    /**
     * New approach: inject a per-connection TypeRegistry with lazy service resolution (DBAL >= 4.5).
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

                    $services[$tag['type_name'] ?? $tag['type'] ?? $id] = new Reference($id);
                }
            }

            $registryId  = sprintf('doctrine.dbal.%s_connection.type_registry', $name);
            $registryRef = new Reference($registryId);

            // Inject a ServiceLocator so types are resolved lazily on first use. The locator is
            // keyed by type name, so the name-to-service-ID map the registry requires is an
            // identity map.
            $locatorRef = ServiceLocatorTagPass::register($container, $services);
            $typeNames  = array_keys($services);
            $container->setDefinition($registryId, new Definition(TypeRegistry::class, [
                $locatorRef,
                array_combine($typeNames, $typeNames),
            ]));

            $container
                ->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))
                ->addMethodCall('setTypeProvider', [$registryRef]);
        }
    }

    /**
     * Fallback approach: register types in the global type registry via doctrine.dbal.connection_factory.types.
     * Used when DBAL does not support TypeRegistry injection (DBAL < 4.5).
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
                $types[$tag['type_name'] ?? $tag['type'] ?? $id] = ['class' => $class];
            }
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }
}
