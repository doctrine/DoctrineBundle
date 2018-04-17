<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DropDatabaseDoctrineTest extends TestCase
{
    public function tearDown()
    {
        @unlink(sys_get_temp_dir().'/test_db');
    }

    public function testExecute()
    {
        $params = [
            'driver'     => 'pdo_sqlite',
            'url'        => 'sqlite:///'.sys_get_temp_dir().'/test_db',
            'path'       => sys_get_temp_dir().'/test_db',
            'connection' => 'default'
        ];

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:drop');
        $command->setContainer($this->getMockContainer('default', $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--force' => true,
        ]);

        $this->assertEquals('Dropped database for connection named ' . $params['path'], trim($commandTester->getDisplay()));
    }

    /**
     * @param $connectionName
     * @param null $params
     * @return \PHPUnit\Framework\MockObject\MockObject
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
