<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class RemoveProfilerControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('twig') && $container->has('profiler')) {
            return;
        }

        $container->removeDefinition('doctrine.profiler_controller');
    }
}
