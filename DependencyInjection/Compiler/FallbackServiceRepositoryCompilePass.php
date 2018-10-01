<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FallbackServiceRepositoryCompilePass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!preg_match('/^doctrine\.orm\.[^.]+_entity_manager$/', $id)) {
                continue;
            }

            $this->processMetadata(
                $container,
                $id,
                $container->get($id)->getMetadataFactory()
            );
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $entityManagerServiceId
     * @param ClassMetadataFactory $factory
     */
    protected function processMetadata(
        ContainerBuilder $container,
        $entityManagerServiceId,
        ClassMetadataFactory $factory
    ) {
        foreach ($factory->getAllMetadata() as $metadata) {
            $repositoryClassName = null;
            if ($metadata->customRepositoryClassName) {
                $repositoryClassName = $metadata->customRepositoryClassName;
            }

            if ($repositoryClassName === null) {
                continue;
            }

            if (!$container->has($repositoryClassName)) {
                $repositoryDefinition = new Definition(
                    $repositoryClassName,
                    [
                        $metadata->getName()
                    ]
                );
                $repositoryDefinition->setFactory([
                    new Reference($entityManagerServiceId),
                    'getRepository'
                ]);
                $repositoryDefinition->addTag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);
                $container->setDefinition(
                    $repositoryClassName,
                    $repositoryDefinition
                );
            }
        }
    }
}
