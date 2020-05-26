<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @group legacy
 */
class ImportMappingDoctrineCommandTest extends TestCase
{
    /** @var TestKernel|null */
    private $kernel;

    /** @var CommandTester|null */
    private $commandTester;

    public static function setUpBeforeClass()
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    protected function setup()
    {
        $this->kernel = new class() extends TestKernel {
            public function registerBundles() : iterable
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

    protected function tearDown()
    {
        $fs = new Filesystem();
        if ($this->kernel !== null) {
            $fs->remove($this->kernel->getCacheDir());
        }

        $fs->remove(sys_get_temp_dir() . '/import_mapping_bundle');
        $this->kernel        = null;
        $this->commandTester = null;
    }

    public function testExecuteXmlWithBundle()
    {
        $this->commandTester->execute(['name' => 'ImportMappingTestFooBundle']);

        $expectedMetadataPath = sys_get_temp_dir() . '/import_mapping_bundle/Resources/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('"Doctrine\Bundle\DoctrineBundle\Tests\Command\Entity\Product"', file_get_contents($expectedMetadataPath), 'Metadata contains correct namespace');
    }

    public function testExecuteAnnotationsWithBundle()
    {
        $this->commandTester->execute([
            'name' => 'ImportMappingTestFooBundle',
            'mapping-type' => 'annotation',
        ]);

        $expectedMetadataPath = sys_get_temp_dir() . '/import_mapping_bundle/Entity/Product.php';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('namespace Doctrine\Bundle\DoctrineBundle\Tests\Command\Entity;', file_get_contents($expectedMetadataPath), 'File contains correct namespace');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /The --path option is required/
     */
    public function testExecuteThrowsExceptionWithNamespaceAndNoPath()
    {
        $this->commandTester->execute(['name' => 'Some\Namespace']);
    }

    public function testExecuteXmlWithNamespace()
    {
        $this->commandTester->execute([
            'name' => 'Some\Namespace\Entity',
            '--path' => $this->kernel->getProjectDir() . '/config/doctrine',
        ]);

        $expectedMetadataPath = $this->kernel->getProjectDir() . '/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('"Some\Namespace\Entity\Product"', file_get_contents($expectedMetadataPath), 'Metadata contains correct namespace');
    }

    public function testExecuteAnnotationsWithNamespace()
    {
        $this->commandTester->execute([
            'name' => 'Some\Namespace\Entity',
            '--path' => $this->kernel->getProjectDir() . '/src/Entity',
            'mapping-type' => 'annotation',
        ]);

        $expectedMetadataPath = $this->kernel->getProjectDir() . '/src/Entity/Product.php';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('namespace Some\Namespace\Entity;', file_get_contents($expectedMetadataPath), 'Metadata contains correct namespace');
    }
}

class ImportMappingTestFooBundle extends Bundle
{
    public function getPath() : string
    {
        return sys_get_temp_dir() . '/import_mapping_bundle';
    }
}
