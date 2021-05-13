<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ManagerRegistry;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
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
        @unlink(sys_get_temp_dir() . '/shard_1');
        @unlink(sys_get_temp_dir() . '/shard_2');
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

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:create');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()])
        );

        $this->assertStringContainsString(
            'Created database ' . sys_get_temp_dir() . '/' . $dbName . ' for connection named ' . $connectionName,
            $commandTester->getDisplay()
        );
    }

    /** @dataProvider provideShardOption */
    public function testExecuteWithShardAlias(string $shardOption): void
    {
        $connectionName = 'default';
        $params         = [
            'dbname' => 'test',
            'memory' => true,
            'driver' => 'pdo_sqlite',
            'global' => [
                'driver' => 'pdo_sqlite',
                'dbname' => 'test',
                'path' => sys_get_temp_dir() . '/global',
            ],
            'shards' => [
                'foo' => [
                    'id' => 1,
                    'path' => sys_get_temp_dir() . '/shard_1',
                    'driver' => 'pdo_sqlite',
                ],
                'bar' => [
                    'id' => 2,
                    'path' => sys_get_temp_dir() . '/shard_2',
                    'driver' => 'pdo_sqlite',
                ],
            ],
        ];

        $container = $this->getMockContainer($connectionName, $params);

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:create');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), $shardOption => 1]);

        $this->assertStringContainsString(
            'Created database ' . sys_get_temp_dir() . '/shard_1 for connection named ' . $connectionName,
            $commandTester->getDisplay()
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), $shardOption => 2]);

        $this->assertStringContainsString(
            'Created database ' . sys_get_temp_dir() . '/shard_2 for connection named ' . $connectionName,
            $commandTester->getDisplay()
        );
    }

    public function provideShardOption(): Generator
    {
        yield 'full name' => ['--shard'];
        yield 'short name' => ['-s'];
    }

    /**
     * @param mixed[]|null $params Connection parameters
     * @psalm-param Params $params
     *
     * @return MockObject&Container
     */
    private function getMockContainer(string $connectionName, ?array $params = null): MockObject
    {
        // Mock the container and everything you'll need here
        $mockDoctrine = $this->getMockBuilder(ManagerRegistry::class)
            ->getMock();

        $mockDoctrine->expects($this->any())
            ->method('getDefaultConnectionName')
            ->withAnyParameters()
            ->willReturn($connectionName);

        $mockConnection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['getParams'])
            ->getMockForAbstractClass();

        $mockConnection->expects($this->any())
            ->method('getParams')
            ->withAnyParameters()
            ->willReturn($params);

        $mockDoctrine->expects($this->any())
            ->method('getConnection')
            ->withAnyParameters()
            ->willReturn($mockConnection);

        $mockContainer = $this->getMockBuilder(Container::class)
            ->setMethods(['get'])
            ->getMock();

        $mockContainer->expects($this->any())
            ->method('get')
            ->with('doctrine')
            ->willReturn($mockDoctrine);

        return $mockContainer;
    }
}
