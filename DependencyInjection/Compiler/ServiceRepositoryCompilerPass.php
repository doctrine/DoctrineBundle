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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class ServiceRepositoryCompilerPass implements CompilerPassInterface
{
    const REPOSITORY_SERVICE_TAG = 'doctrine.repository_service';

    public function process(ContainerBuilder $container)
    {
        // when ORM is not enabled
        if (!$container->hasDefinition('doctrine.orm.container_repository_factory')) {
            return;
        }

        $locatorDef = $container->getDefinition('doctrine.orm.container_repository_factory');

        $repoServiceIds = array_keys($container->findTaggedServiceIds(self::REPOSITORY_SERVICE_TAG));

        // Symfony 3.2 and lower sanity check
        if (!class_exists(ServiceLocatorTagPass::class)) {
            if (!empty($repoServiceIds)) {
                throw new RuntimeException(sprintf('The "%s" tag can only be used with Symfony 3.3 or higher. Remove the tag from the following services (%s) or upgrade to Symfony 3.3 or higher.', self::REPOSITORY_SERVICE_TAG, implode(', ', $repoServiceIds)));
            }

            return;
        }

        $repoReferences = array_map(function ($id) {
            return new Reference($id);
        }, $repoServiceIds);

        $ref = ServiceLocatorTagPass::register($container, array_combine($repoServiceIds, $repoReferences));
        $locatorDef->replaceArgument(0, $ref);
    }
}
