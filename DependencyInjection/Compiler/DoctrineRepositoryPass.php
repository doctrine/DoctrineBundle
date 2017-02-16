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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configure DI Repository Factory with repositories with dependencies
 *
 * @author Miguel Angel Garzón <magarzon@gmail.com>
 */
class DoctrineRepositoryPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('doctrine.di_repository_factory')) {
            $servicesId = $container->findTaggedServiceIds('doctrine.repository');

            $definitions = [];

            foreach ($servicesId as $serviceId => $parameters) {
                $definition = $container->getDefinition($serviceId);
                $definitions[] = $this->createFromServiceDefinition($definition);
            }

            $factory = $container->getDefinition('doctrine.di_repository_factory');
            $factory->addArgument($definitions);
        }
    }

    /**
     * @param Definition $definition
     * @return array
     */
    private function createFromServiceDefinition(Definition $definition)
    {
        $tag = $definition->getTag('doctrine.repository');
        $entity = $tag[0]['entity'];
        //Only setter injection by now
        $calls = $definition->getMethodCalls();

        return ['entity' => $entity, 'setters' => $calls];
    }
}
