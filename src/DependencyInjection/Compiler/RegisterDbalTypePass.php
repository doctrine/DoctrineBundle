<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\DBAL\Types\Type;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use function is_subclass_of;
use function method_exists;
use function sprintf;

/** @internal */
final class RegisterDbalTypePass implements CompilerPassInterface
{
    private const string TAG = 'doctrine.dbal.type';

    /**
     * @param ReflectionClass<T> $reflector
     *
     * @template T of ReflectionClass
     */
    public static function autoconfigureFromAttribute(ChildDefinition $definition, AsDbalType $type, ReflectionClass $reflector): void
    {
        $attributes = [
            'type_name' => $type->name ?? $reflector->name,
        ];

        // Determine if the version of symfony/dependency-injection is >= 7.3
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (method_exists($definition, 'addResourceTag')) {
            $definition->addResourceTag(self::TAG, $attributes);
        } else {
            // Needed to keep compatibility with symfony/dependency-injection < 7.3
            $definition->addTag(self::TAG, $attributes)
                ->addTag('container.excluded', ['source' => sprintf('by tag "%s"', self::TAG)]);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        $types = $container->getParameter('doctrine.dbal.connection_factory.types');

        foreach ($this->findTaggedResourceIds($container) as $id => $tags) {
            foreach ($tags as $tag) {
                $class = $container->getDefinition($id)->getClass();
                if (! $class) {
                    throw new InvalidArgumentException(sprintf('The definition of "%s" must define its class.', $id));
                }

                if (! is_subclass_of($class, Type::class)) {
                    throw new InvalidArgumentException(sprintf('The "%s" class must extends "%s".', $class, Type::class));
                }

                $types[$tag['type_name'] ?? $id] = ['class' => $class];
            }
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }

    /** @return array<string, array<array{type_name?: string}>> */
    private function findTaggedResourceIds(ContainerBuilder $container): array
    {
        // Determine if the version of symfony/dependency-injection is >= 7.3
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (method_exists($container, 'findTaggedResourceIds')) {
            return $container->findTaggedResourceIds(self::TAG);
        }

        // Needed to keep compatibility with symfony/dependency-injection < 7.3
        $tags = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (! $definition->hasTag(self::TAG)) {
                continue;
            }

            if (! $definition->hasTag('container.excluded')) {
                throw new InvalidArgumentException(sprintf('The resource "%s" tagged "%s" is missing the "container.excluded" tag.', $id, self::TAG));
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (! $class || $definition->isAbstract()) {
                throw new InvalidArgumentException(sprintf('The resource "%s" tagged "%s" must have a class and not be abstract.', $id, self::TAG));
            }

            if ($definition->getClass() !== $class) {
                $definition->setClass($class);
            }

            $tags[$id] = $definition->getTag(self::TAG);
        }

        return $tags;
    }
}
