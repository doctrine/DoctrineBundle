<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataFactory;
use Doctrine\Bundle\DoctrineBundle\Mapping\MappingDriver;
use Doctrine\ORM\Mapping\ClassMetadataFactory as ORMClassMetadataFactory;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_combine;
use function array_keys;
use function array_map;
use function sprintf;

/** @internal */
final class IdGeneratorPass implements CompilerPassInterface
{
    public const string ID_GENERATOR_TAG  = 'doctrine.id_generator';
    public const string CONFIGURATION_TAG = 'doctrine.orm.configuration';

    public function process(ContainerBuilder $container): void
    {
        $generatorIds = array_keys($container->findTaggedServiceIds(self::ID_GENERATOR_TAG));

        // when ORM is not enabled
        if (! $container->hasDefinition('doctrine.orm.configuration') || ! $generatorIds) {
            return;
        }

        $generatorRefs = array_map(static fn (string $id): Reference => new Reference($id), $generatorIds);

        $ref = ServiceLocatorTagPass::register($container, array_combine($generatorIds, $generatorRefs));
        $container->setAlias('doctrine.id_generator_locator', new Alias((string) $ref, false));

        foreach ($container->findTaggedServiceIds(self::CONFIGURATION_TAG) as $id => $tags) {
            $configurationDef   = $container->getDefinition($id);
            $methodCalls        = $configurationDef->getMethodCalls();
            $metadataDriverImpl = null;

            foreach ($methodCalls as $i => [$method, $arguments]) {
                if ($method === 'setMetadataDriverImpl') {
                    $metadataDriverImpl = (string) $arguments[0];
                }

                if ($method !== 'setClassMetadataFactoryName') {
                    continue;
                }

                if ($arguments[0] !== ORMClassMetadataFactory::class && $arguments[0] !== ClassMetadataFactory::class) {
                    $class = $container->getReflectionClass($arguments[0]);

                    if ($class && $class->isSubclassOf(ClassMetadataFactory::class)) {
                        break;
                    }

                    continue 2;
                }

                $methodCalls[$i] = ['setClassMetadataFactoryName', [ClassMetadataFactory::class]];
            }

            if ($metadataDriverImpl === null) {
                continue;
            }

            $configurationDef->setMethodCalls($methodCalls);
            $container->register('.' . $metadataDriverImpl, MappingDriver::class)
                ->setDecoratedService($metadataDriverImpl)
                ->setArguments([
                    new Reference(sprintf('.%s.inner', $metadataDriverImpl)),
                    new Reference('doctrine.id_generator_locator'),
                ]);
        }
    }
}
