<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function interface_exists;

class DoctrineOrmMappingsPassTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testCreateXmlMappingDriver(): void
    {
        $namespaces = ['App\Entity' => '/path/to/mapping'];
        $pass       = DoctrineOrmMappingsPass::createXmlMappingDriver($namespaces);

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');

        $chainDriverDef = new Definition();
        $container->setDefinition('doctrine.orm.default_metadata_driver', $chainDriverDef);

        $pass->process($container);

        $methodCalls = $chainDriverDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addDriver', $methodCalls[0][0]);

        $driverDef = $methodCalls[0][1][0];
        $this->assertInstanceOf(Definition::class, $driverDef);

        $this->assertSame(XmlDriver::class, $driverDef->getClass());

        $args       = $driverDef->getArguments();
        $locatorDef = $args[0];
        $this->assertInstanceOf(Definition::class, $locatorDef);
        $this->assertSame(SymfonyFileLocator::class, $locatorDef->getClass());
        $this->assertSame($namespaces, $locatorDef->getArgument(0));
        $this->assertSame('.orm.xml', $locatorDef->getArgument(1));

        $this->assertSame(XmlDriver::DEFAULT_FILE_EXTENSION, $args[1]);
        $this->assertFalse($args[2]);
    }

    public function testCreatePhpMappingDriver(): void
    {
        $namespaces = ['App\Entity' => '/path/to/mapping'];
        $pass       = DoctrineOrmMappingsPass::createPhpMappingDriver($namespaces);

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');

        $chainDriverDef = new Definition();
        $container->setDefinition('doctrine.orm.default_metadata_driver', $chainDriverDef);

        $pass->process($container);

        $methodCalls = $chainDriverDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addDriver', $methodCalls[0][0]);

        $driverDef = $methodCalls[0][1][0];
        $this->assertInstanceOf(Definition::class, $driverDef);

        $this->assertSame(PHPDriver::class, $driverDef->getClass());

        $args       = $driverDef->getArguments();
        $locatorDef = $args[0];
        $this->assertInstanceOf(Definition::class, $locatorDef);
        $this->assertSame(SymfonyFileLocator::class, $locatorDef->getClass());
        $this->assertSame($namespaces, $locatorDef->getArgument(0));
        $this->assertSame('.php', $locatorDef->getArgument(1));
    }

    public function testCreateAttributeMappingDriver(): void
    {
        $namespaces  = ['App\Entity'];
        $directories = ['/path/to/mapping'];
        $pass        = DoctrineOrmMappingsPass::createAttributeMappingDriver($namespaces, $directories);

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');

        $chainDriverDef = new Definition();
        $container->setDefinition('doctrine.orm.default_metadata_driver', $chainDriverDef);

        $pass->process($container);

        $methodCalls = $chainDriverDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addDriver', $methodCalls[0][0]);

        $driverDef = $methodCalls[0][1][0];
        $this->assertInstanceOf(Definition::class, $driverDef);

        $this->assertSame(AttributeDriver::class, $driverDef->getClass());

        $args = $driverDef->getArguments();
        $this->assertSame($directories, $args[0]);
    }

    public function testCreateStaticPhpMappingDriver(): void
    {
        $namespaces  = ['App\Entity'];
        $directories = ['/path/to/mapping'];
        $pass        = DoctrineOrmMappingsPass::createStaticPhpMappingDriver($namespaces, $directories);

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');

        $chainDriverDef = new Definition();
        $container->setDefinition('doctrine.orm.default_metadata_driver', $chainDriverDef);

        $pass->process($container);

        $methodCalls = $chainDriverDef->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('addDriver', $methodCalls[0][0]);

        $driverDef = $methodCalls[0][1][0];
        $this->assertInstanceOf(Definition::class, $driverDef);

        $this->assertSame(StaticPHPDriver::class, $driverDef->getClass());

        $args = $driverDef->getArguments();
        $this->assertSame($directories, $args[0]);
    }
}
