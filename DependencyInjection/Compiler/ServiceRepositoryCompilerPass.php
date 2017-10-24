<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ServiceRepositoryCompilerPass implements CompilerPassInterface
{
    const REPOSITORY_SERVICE_TAG = 'doctrine.repository_service';

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine.orm.container_repository_factory')) {
            return;
        }

        $locatorDef = $container->findDefinition($container->getDefinition('doctrine.orm.container_repository_factory')->getArgument(0));
        $repoServiceIds = array_keys($container->findTaggedServiceIds(self::REPOSITORY_SERVICE_TAG));
        $repoReferences = array_map(function($id) {
            return new Reference($id);
        }, $repoServiceIds);
        $locatorDef->replaceArgument(0, array_combine($repoServiceIds, $repoReferences));
    }
}
