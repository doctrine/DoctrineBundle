<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Controller\ProfilerController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class RemoveProfilerControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->has('twig') && $container->has('profiler')) {
            return;
        }

        $container->removeDefinition(ProfilerController::class);
    }
}
