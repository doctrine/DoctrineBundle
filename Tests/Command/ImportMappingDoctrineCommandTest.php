<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function class_exists;
use function file_get_contents;
use function interface_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/** @group legacy */
class ImportMappingDoctrineCommandTest extends TestCase
{
    private TestKernel $kernel;
    private CommandTester $commandTester;

    public static function setUpBeforeClass(): void
    {
        if (PHP_VERSION_ID < 80000 && ! class_exists(AnnotationReader::class)) {
            self::markTestSkipped('This test requires Annotations when run on PHP 7');
        }

        if (interface_exists(EntityManagerInterface::class) && class_exists(ClassMetadataExporter::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM version 2');
    }

    protected function setup(): void
    {
        $this->kernel = new class () extends TestKernel {
            /** @return iterable<Bundle> */
            public function registerBundles(): iterable
            {
                yield from parent::registerBundles();
                yield new ImportMappingTestFooBundle();
            }
        };

        $this->kernel->boot();

        $connection = $this->kernel->getContainer()
            ->get('doctrine')
            ->getConnection();
        $connection->executeQuery('CREATE TABLE product (id integer primary key, name varchar(20), hint text)');

        $application         = new Application($this->kernel);
        $command             = $application->find('doctrine:mapping:import');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (isset($this->kernel)) {
            $fs->remove($this->kernel->getCacheDir());
        }

        $fs->remove(sys_get_temp_dir() . '/import_mapping_bundle');
        unset($this->kernel, $this->commandTester);
    }

    public function testExecuteXmlWithBundle(): void
    {
        $this->commandTester->execute(['name' => 'ImportMappingTestFooBundle']);

        $expectedMetadataPath = sys_get_temp_dir() . '/import_mapping_bundle/Resources/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertStringContainsString(
            '"Doctrine\Bundle\DoctrineBundle\Tests\Command\Entity\Product"',
            file_get_contents($expectedMetadataPath),
            'Metadata contains correct namespace'
        );
    }

    public function testExecuteAnnotationsWithBundle(): void
    {
        $this->commandTester->execute([
            'name' => 'ImportMappingTestFooBundle',
            'mapping-type' => 'annotation',
        ]);

        $expectedMetadataPath = sys_get_temp_dir() . '/import_mapping_bundle/Entity/Product.php';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertStringContainsString(
            'namespace Doctrine\Bundle\DoctrineBundle\Tests\Command\Entity;',
            file_get_contents($expectedMetadataPath),
            'File contains correct namespace'
        );
    }

    public function testExecuteThrowsExceptionWithNamespaceAndNoPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --path option is required');
        $this->commandTester->execute(['name' => 'Some\Namespace']);
    }

    public function testExecuteXmlWithNamespace(): void
    {
        $this->commandTester->execute([
            'name' => 'Some\Namespace\Entity',
            '--path' => $this->kernel->getProjectDir() . '/config/doctrine',
        ]);

        $expectedMetadataPath = $this->kernel->getProjectDir() . '/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertStringContainsString(
            '"Some\Namespace\Entity\Product"',
            file_get_contents($expectedMetadataPath),
            'Metadata contains correct namespace'
        );
    }

    public function testExecuteAnnotationsWithNamespace(): void
    {
        $this->commandTester->execute([
            'name' => 'Some\Namespace\Entity',
            '--path' => $this->kernel->getProjectDir() . '/src/Entity',
            'mapping-type' => 'annotation',
        ]);

        $expectedMetadataPath = $this->kernel->getProjectDir() . '/src/Entity/Product.php';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertStringContainsString(
            'namespace Some\Namespace\Entity;',
            file_get_contents($expectedMetadataPath),
            'Metadata contains correct namespace'
        );
    }
}

class ImportMappingTestFooBundle extends Bundle
{
    public function getPath(): string
    {
        return sys_get_temp_dir() . '/import_mapping_bundle';
    }
}
