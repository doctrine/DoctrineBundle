<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

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
        }

        $rootConflicts = [];
        foreach ($customRepositories as $repositoryClass => $entities) {
            $repoConflicts = $this->findConflictingServices($container, $repositoryClass);

            if (count($repoConflicts)) {
                $rootConflicts[$repositoryClass] = $repoConflicts;
            }
        }

        foreach ($customRepositories as $repositoryClass => $entities) {
            if ($container->has($repositoryClass)) {
                continue;
            }

            if (count($entities) !== 1) {
                $this->log($container, "Cannot auto-register repository \"".$repositoryClass."\": Entity belongs to multiple entity managers.");
                continue;
            }

            if (isset($rootConflicts[$repositoryClass])) {
                $this->log($container, "Cannot auto-register repository \"".$repositoryClass."\": There are already services for the repository class.");
                continue;
            }

            $definition = $container->register($repositoryClass, $repositoryClass)
                ->setFactory([new Reference('doctrine'), 'getRepository'])
                ->setArguments($entities[0])
                ->setPublic(false)
            ;

            if (Kernel::MAJOR_VERSION <= 2 && Kernel::MINOR_VERSION <= 7) {
                $definition->setScope('prototype');
            } else {
                $definition->setShared(false);
            }
        }

    }

    private function findConflictingServices(ContainerBuilder $container, $repositoryClass)
    {
        if (Kernel::MAJOR_VERSION >= 4) {
            return [];
        }

        $conflictingServices = [];
        $parameterBag = $container->getParameterBag();

        foreach ($container->getDefinitions() as $id => $definition) {
            $defClass = $parameterBag->resolveValue($definition->getClass());

            if ($defClass != $repositoryClass) {
                continue;
            }

            $conflictingServices[] = $id;
        }

        return $conflictingServices;
    }

    private function log(ContainerBuilder $container, $message)
    {
        if (method_exists($container, 'log')) {
            $container->log($this, $message);
        }
    }
}
