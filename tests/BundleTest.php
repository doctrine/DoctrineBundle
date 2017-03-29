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

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;

class BundleTest extends \PHPUnit_Framework_TestCase
{
    public function testBuildCompilerPasses()
    {
        $container = new ContainerBuilder();
        $bundle = new DoctrineBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundEventListener = false;
        $foundValidation = false;

        foreach ($passes as $pass) {
            if ($pass instanceof RegisterEventListenersAndSubscribersPass) {
                $foundEventListener = true;
            } elseif ($pass instanceof DoctrineValidationPass) {
                $foundValidation = true;
            }
        }

        $this->assertTrue($foundEventListener, 'RegisterEventListenersAndSubscribersPass was not found');
        $this->assertTrue($foundValidation, 'DoctrineValidationPass was not found');
    }
}
