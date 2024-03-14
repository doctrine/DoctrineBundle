<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function strpos;

class TestCaseAllPublicCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (strpos($id, 'doctrine') === false) {
                continue;
            }

            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $id => $alias) {
            if (strpos($id, 'doctrine') === false) {
                continue;
            }

            $alias->setPublic(true);
        }
    }
}
