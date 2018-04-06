<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DropDatabaseDoctrineTest extends TestCase
{
    public function testExecute()
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'url' => 'sqlite:///' . sys_get_temp_dir() . '/test.db',
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:drop');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName(), '--force' => true])
        );

        $this->assertContains('Dropped database for connection named ' . sys_get_temp_dir() . '/' . $dbName . '', $commandTester->getDisplay());
    }

    public function testExecuteWithoutOptionForceWillFailWithAttentionMessage()
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:drop');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()])
        );

        $this->assertContains('Would drop the database named ' . sys_get_temp_dir() . '/' . $dbName . '.', $commandTester->getDisplay());
        $this->assertContains('Please run the operation with --force to execute', $commandTester->getDisplay());
    }

    /**
     * @param string     $connectionName Connection name
     * @param array|null $params         Connection parameters
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
