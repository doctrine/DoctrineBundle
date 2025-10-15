<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

#[IgnoreDeprecations]
class XmlDoctrineExtensionTest extends AbstractDoctrineExtensionTestCase
{
    protected function loadFromFile(
        ContainerBuilder $container,
        string $file,
    ): void {
        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Fixtures/config/xml'));
        $loadXml->import($file . '.{xml}');
    }
}
