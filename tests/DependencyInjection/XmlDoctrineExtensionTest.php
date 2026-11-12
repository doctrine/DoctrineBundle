<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader as LoaderXmlFileLoader;

#[IgnoreDeprecations]
#[RequiresMethod(LoaderXmlFileLoader::class, 'loadFile')]
class XmlDoctrineExtensionTest extends AbstractDoctrineExtensionTestCase
{
    protected function loadFromFile(
        ContainerBuilder $container,
        string $file,
    ): void {
        /** @phpstan-ignore class.notFound */
        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Fixtures/config/xml'));
        /** @phpstan-ignore class.notFound */
        $loadXml->import($file . '.{xml}');
    }
}
