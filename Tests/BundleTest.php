<?php


namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BundleTest extends TestCase
{
    public function testBuildCompilerPasses()
    {
        $container = new ContainerBuilder();
        $bundle    = new DoctrineBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundEventListener = false;
        $foundValidation    = false;

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
