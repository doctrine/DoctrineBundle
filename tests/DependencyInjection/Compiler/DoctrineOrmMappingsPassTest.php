<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function assert;
use function interface_exists;
use function realpath;

class DoctrineOrmMappingsPassTest extends TestCase
{
    use VerifyDeprecations;

    #[IgnoreDeprecations]
    public function testCreateYamlMappingDriverIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/DoctrineBundle/pull/2088');

        DoctrineOrmMappingsPass::createYamlMappingDriver(['/path/to/namespace' => 'App\\Entity']);
    }

    #[IgnoreDeprecations]
    public function testCreateAnnotationMappingDriverIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/DoctrineBundle/pull/2088');

        DoctrineOrmMappingsPass::createAnnotationMappingDriver(
            ['App\\Entity'],
            ['/path/to/entities'],
        );
    }

    public function testAttributeDriverIsRegistered(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $driverNamespace = 'DoctrineBundle\Entity';
        $container       = $this->createXmlBundleTestContainer(
            static function (ContainerBuilder $containerBuilder) use ($driverNamespace): void {
                $containerBuilder->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                    [$driverNamespace],
                    [realpath(__DIR__ . '/Entity')],
                    reportFieldsWhereDeclared: true,
                ));
            },
        );

        $metadataDriver = $container->get('doctrine.orm.default_metadata_driver');
        assert($metadataDriver instanceof MappingDriverChain);

        $driver = $metadataDriver->getDrivers()[$driverNamespace];
        $this->assertTrue($driver instanceof AttributeDriver);
    }
}
