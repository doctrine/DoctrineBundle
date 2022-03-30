<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RemoveLoggingMiddlewarePass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class RemoveLoggingMiddlewarePassTest extends TestCase
{
    public function testLoggingMiddlewareRemovedWhenLoggerMissing(): void
    {
        $container = $this->createContainer();
        $container->compile();

        $this->assertFalse($container->hasDefinition('logging_middleware_child'));
    }

    public function testLoggingMiddlewareNotRemovedWhenLoggerPresent(): void
    {
        $container = $this->createContainer();

        $logger = (new Definition())
            ->setClass(NullLogger::class);
        $container->setDefinition('logger', $logger);

        $container->compile();

        $this->assertTrue($container->hasDefinition('logging_middleware_child'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../Resources/config'));
        $loader->load('middlewares.xml');

        $container->addCompilerPass(new RemoveLoggingMiddlewarePass());
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container)
            {
                if (! $container->hasDefinition('doctrine.dbal.logging_middleware')) {
                    return;
                }

                $loggingMiddlewareChild = (new ChildDefinition('doctrine.dbal.logging_middleware'))
                    ->setPublic(true);
                $container->setDefinition('logging_middleware_child', $loggingMiddlewareChild);
            }
        });

        return $container;
    }
}
