<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @group legacy
 */
class ImportMappingDoctrineCommandTest extends TestCase
{
    /** @var ImportMappingTestingKernel|null */
    private $kernel;

    /** @var CommandTester|null */
    private $commandTester;

    protected function setup() : void
    {
        $this->kernel = new ImportMappingTestingKernel();
        $this->kernel->boot();

        $connection = $this->kernel->getContainer()
            ->get('doctrine')
            ->getConnection();
        $connection->executeQuery('CREATE TABLE product (id integer primary key, name varchar(20), hint text)');

        $application         = new Application($this->kernel);
        $command             = $application->find('doctrine:mapping:import');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown() : void
    {
        $fs = new Filesystem();
        if ($this->kernel !== null) {
            $fs->remove($this->kernel->getCacheDir());
        }

        $fs->remove(sys_get_temp_dir() . '/import_mapping_bundle');
        $this->kernel        = null;
        $this->commandTester = null;
    }

    public function testExecuteXmlWithBundle() : void
    {
        $this->commandTester->execute(['name' => 'ImportMappingTestFooBundle']);

        $expectedMetadataPath = sys_get_temp_dir() . '/import_mapping_bundle/Resources/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('"Doctrine\Bundle\DoctrineBundle\Tests\Command\Entity\Product"', file_get_contents($expectedMetadataPath), 'Metadata contains correct namespace');
    }

    public function testExecuteAnnotationsWithBundle() : void
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
    public function testExecuteThrowsExceptionWithNamespaceAndNoPath() : void
    {
        $this->commandTester->execute(['name' => 'Some\Namespace']);
    }

    public function testExecuteXmlWithNamespace() : void
    {
        $this->commandTester->execute([
            'name' => 'Some\Namespace\Entity',
            '--path' => $this->kernel->getProjectDir() . '/config/doctrine',
        ]);

        $expectedMetadataPath = $this->kernel->getProjectDir() . '/config/doctrine/Product.orm.xml';
        $this->assertFileExists($expectedMetadataPath);
        $this->assertContains('"Some\Namespace\Entity\Product"', file_get_contents($expectedMetadataPath), 'Metadata contains correct namespace');
    }

    public function testExecuteAnnotationsWithNamespace() : void
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

class ImportMappingTestingKernel extends Kernel
{
    /** @var string|null */
    private $projectDir;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles() : iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new ImportMappingTestFooBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader) : void
    {
        $loader->load(function (ContainerBuilder $container) : void {
            $container->loadFromExtension('framework', ['secret' => 'F00']);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'path' => $this->getCacheDir() . '/testing.db',
                ],
                'orm' => [],
            ]);

            // Register a NullLogger to avoid getting the stderr default logger of FrameworkBundle
            $container->register('logger', NullLogger::class);
        });
    }

    public function getProjectDir() : string
    {
        if ($this->projectDir === null) {
            $this->projectDir = sys_get_temp_dir() . '/sf_kernel_' . md5(mt_rand());
        }

        return $this->projectDir;
    }

    public function getRootDir() : string
    {
        return $this->getProjectDir();
    }
}

class ImportMappingTestFooBundle extends Bundle
{
    public function getPath() : string
    {
        return sys_get_temp_dir() . '/import_mapping_bundle';
    }
}
