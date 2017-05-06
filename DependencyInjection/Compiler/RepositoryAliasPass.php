<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RepositoryAliasPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('doctrine.entity_managers')) {
            return;
        }

        $entityManagers = $container->getParameter('doctrine.entity_managers');
        $customRepositories = [];

        foreach ($entityManagers as $name => $serviceName) {
            $metadataDriverService = sprintf('doctrine.orm.%s_metadata_driver', $name);

            if (!$container->has($metadataDriverService)) {
                continue;
            }

            /** @var MappingDriver $metadataDriver */
            $metadataDriver = $container->get($metadataDriverService);
            $entityClassNames = $metadataDriver->getAllClassNames();

            foreach ($entityClassNames as $entityClassName) {
                $classMetadata = new ClassMetadata($entityClassName);
                $metadataDriver->loadMetadataForClass($entityClassName, $classMetadata);

                if ($classMetadata->customRepositoryClassName) {
                    $customRepositories[$classMetadata->customRepositoryClassName][] = [
                        0 => $entityClassName,
                        1 => $name,
                    ];
                }
            }

            foreach ($customRepositories as $repositoryClass => $entities) {
                if (count($entities) === 1) {
                    $definition = new Definition($repositoryClass);
                    $definition->setFactory(array(
                        new Reference('doctrine'),
                        'getRepository'
                    ));
                    $definition->setArguments($entities[0]);
                    $definition->setShared(false);

                    $container->setDefinition($repositoryClass, $definition);
                }
            }
        }
    }
}
