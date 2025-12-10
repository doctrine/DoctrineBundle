<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Tests\Polyfill\SymfonyApp;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

use function array_merge;
use function sys_get_temp_dir;
use function unlink;

/** @psalm-import-type Params from DriverManager */
class CreateDatabaseDoctrineTest extends TestCase
{
    public function tearDown(): void
    {
        @unlink(sys_get_temp_dir() . '/test');
    }

    public function testExecute(): void
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $container = $this->getMockContainer($connectionName, $params);

        $application = new SymfonyApp();
        $application->addCommand(new CreateDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:create');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()]),
        );

        $this->assertStringContainsString(
            'Created database ' . sys_get_temp_dir() . '/' . $dbName . ' for connection named ' . $connectionName,
            $commandTester->getDisplay(),
        );
    }

    /**
     * @param mixed[]|null $params Connection parameters
     * @psalm-param Params $params
     *
     * @return Stub&Container
     */
    private function getMockContainer(string $connectionName, array|null $params = null): Stub
    {
        // Mock the container and everything you'll need here
        $mockDoctrine = $this->createStub(ManagerRegistry::class);

        $mockDoctrine->method('getDefaultConnectionName')->willReturn($connectionName);

        $config = (new Configuration())->setSchemaManagerFactory($this->createStub(SchemaManagerFactory::class));

        $mockConnection = $this->createStub(Connection::class);
        $mockConnection->method('getConfiguration')->willReturn($config);
        $mockConnection->method('getParams')->willReturn($params);
        $mockDoctrine->method('getConnection')->willReturn($mockConnection);

        $mockContainer = $this->createStub(Container::class);

        $mockContainer->method('get')->with('doctrine')->willReturn($mockDoctrine);

        return $mockContainer;
    }
}
