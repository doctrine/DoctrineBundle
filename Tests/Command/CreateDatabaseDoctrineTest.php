<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDatabaseDoctrineTest extends TestCase
{
    public function tearDown()
    {
        @unlink(sys_get_temp_dir() . '/test');
        @unlink(sys_get_temp_dir() . '/shard_1');
        @unlink(sys_get_temp_dir() . '/shard_2');
    }

    public function testExecute()
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:create');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()])
        );

        $this->assertContains('Created database ' . sys_get_temp_dir() . '/' . $dbName . ' for connection named ' . $connectionName, $commandTester->getDisplay());
    }

    public function testExecuteWithShardOption()
    {
        $connectionName = 'default';
        $params         = [
            'dbname' => 'test',
            'memory' => true,
            'driver' => 'pdo_sqlite',
            'global' => [
                'driver' => 'pdo_sqlite',
                'dbname' => 'test',
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

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:create');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--shard' => 1]);

        $this->assertContains('Created database ' . sys_get_temp_dir() . '/shard_1 for connection named ' . $connectionName, $commandTester->getDisplay());

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--shard' => 2]);

        $this->assertContains('Created database ' . sys_get_temp_dir() . '/shard_2 for connection named ' . $connectionName, $commandTester->getDisplay());
    }

    /**
     * @param string       $connectionName Connection name
     * @param mixed[]|null $params         Connection parameters
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockContainer($connectionName, $params = null)
    {
        // Mock the container and everything you'll need here
        $mockDoctrine = $this->getMockBuilder('Doctrine\Common\Persistence\ConnectionRegistry')
            ->getMock();

        $mockDoctrine->expects($this->any())
            ->method('getDefaultConnectionName')
            ->withAnyParameters()
            ->willReturn($connectionName);

        $mockConnection = $this->getMockBuilder('Doctrine\DBAL\Connection')
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

        $mockContainer = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->setMethods(['get'])
            ->getMock();

        $mockContainer->expects($this->any())
            ->method('get')
            ->with('doctrine')
            ->willReturn($mockDoctrine);

        return $mockContainer;
    }
}
