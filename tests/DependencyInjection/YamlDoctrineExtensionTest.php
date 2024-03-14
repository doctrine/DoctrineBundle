<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class YamlDoctrineExtensionTest extends AbstractDoctrineExtensionTest
{
    protected function loadFromFile(ContainerBuilder $container, string $file): void
    {
        $loadYaml = new YamlFileLoader($container, new FileLocator(__DIR__ . '/Fixtures/config/yml'));
        $loadYaml->import($file . '.{yml}');
    }
}
