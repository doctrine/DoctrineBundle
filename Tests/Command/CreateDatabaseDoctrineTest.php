<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDatabaseDoctrineTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $connectionName = 'default';
        $dbName = 'test';
        $params = array(
            'dbname' => $dbName,
            'memory' => true,
            'driver' => 'pdo_sqlite',
        );

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:create');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(array('command' => $command->getName()))
        );

        $this->assertContains("Created database \"$dbName\" for connection named $connectionName", $commandTester->getDisplay());
    }

    public function testExecuteWithShardOption()
    {
        $connectionName = 'default';
        $params = array(
            'dbname' => 'test',
            'memory' => true,
            'driver' => 'pdo_sqlite',
            'global' => array(
                'driver' => 'pdo_sqlite',
                'dbname' => 'test',
            ),
            'shards' => array(
                'foo' => array(
                    'id' => 1,
                    'dbname' => 'shard_1',
                    'driver' => 'pdo_sqlite',
                )
            )
        );

        $application = new Application();
        $application->add(new CreateDatabaseDoctrineCommand());

        $command = $application->find('doctrine:database:create');
        $command->setContainer($this->getMockContainer($connectionName, $params));

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--shard' => 1));

        $this->assertContains("Created database \"shard_1\" for connection named $connectionName", $commandTester->getDisplay());
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
            ->setMethods(array('getParams'))
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
            ->setMethods(array('get'))
            ->getMock();

        $mockContainer->expects($this->any())
            ->method('get')
            ->with('doctrine')
            ->willReturn($mockDoctrine);

        return $mockContainer;
    }
}
