<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class to register repositories as services
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
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

        foreach ($customRepositories as $repositoryClass => $entities) {
            if ($container->has($repositoryClass)) {
                continue;
            }

            if (count($entities) !== 1) {
                $this->log($container, "Cannot auto-register repository \"".$repositoryClass."\": Entity belongs to multiple entity managers.");
                continue;
            }

            if ($this->hasConflictingServices($container, $repositoryClass)) {
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

    private function hasConflictingServices(ContainerBuilder $container, $repositoryClass)
    {
        if (Kernel::MAJOR_VERSION >= 4) {
            return false;
        }

        $parameterBag = $container->getParameterBag();

        foreach ($container->getDefinitions() as $id => $definition) {
            $defClass = $parameterBag->resolveValue($definition->getClass());

            if ($defClass == $repositoryClass) {
                return true;
            }
        }

        return false;
    }

    private function log(ContainerBuilder $container, $message)
    {
        if (method_exists($container, 'log')) {
            $container->log($this, $message);
        }
    }
}
