<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;

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
        $foundValidation = true;
        foreach ($passes as $pass) {
            if ($pass instanceof RegisterEventListenersAndSubscribersPass) {
                $foundEventListener = true;
            } else if ($pass instanceof DoctrineValidationPass) {
                $foundValidation = true;
            }
        }
        
        $this->assertTrue($foundEventListener, "Not found the RegisterEventListenersAndSubcribersPass");
        $this->assertTrue($foundEventListener, "Not found the DoctrineValidationPass");
    }
}
