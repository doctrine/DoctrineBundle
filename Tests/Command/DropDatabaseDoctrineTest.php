<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function array_merge;
use function class_exists;
use function sprintf;
use function sys_get_temp_dir;

/**
 * @psalm-import-type Params from \Doctrine\DBAL\DriverManager
 */
class DropDatabaseDoctrineTest extends TestCase
{
    /**
     * @param array<string, bool> $options
     *
     * @dataProvider provideForceOption
     */
    public function testExecute(array $options): void
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'url' => 'sqlite:///' . sys_get_temp_dir() . '/test.db',
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $container = $this->getMockContainer($connectionName, $params);

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:drop');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()], $options)
        );

        $this->assertStringContainsString(
            sprintf(
                'Dropped database %s for connection named %s',
                sys_get_temp_dir() . '/' . $dbName,
                $connectionName
            ),
            $commandTester->getDisplay()
        );
    }

    /**
     * @param array<string, bool> $options
     *
     * @dataProvider provideIncompatibleDriverOptions
     */
    public function testItThrowsWhenUsingIfExistsWithAnIncompatibleDriver(array $options): void
    {
        if (class_exists(DBALException::class)) {
            $this->expectException(DBALException::class);
        } else {
            $this->expectException(Exception::class);
        }

        $this->testExecute($options);
    }

    public function testExecuteWithoutOptionForceWillFailWithAttentionMessage(): void
    {
        $connectionName = 'default';
        $dbName         = 'test';
        $params         = [
            'path' => sys_get_temp_dir() . '/' . $dbName,
            'driver' => 'pdo_sqlite',
        ];

        $container = $this->getMockContainer($connectionName, $params);

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:drop');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()])
        );

        $this->assertStringContainsString(
            sprintf(
                'Would drop the database %s for connection named %s.',
                sys_get_temp_dir() . '/' . $dbName,
                $connectionName
            ),
            $commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Please run the operation with --force to execute',
            $commandTester->getDisplay()
        );
    }

    public function provideForceOption(): Generator
    {
        yield 'full name' => [
            ['--force' => true],
        ];

        yield 'short name' => [
            ['-f' => true],
        ];
    }

    public function provideIncompatibleDriverOptions(): Generator
    {
        yield 'full name' => [
            [
                '--force' => true,
                '--if-exists' => true,
            ],
        ];

        yield 'short name' => [
            [
                '-f' => true,
                '--if-exists' => true,
            ],
        ];
    }

    /**
     * @param list<mixed> $params Connection parameters
     *
     * @psalm-param Params $params
     */
    private function getMockContainer(string $connectionName, array $params): MockObject
    {
        // Mock the container and everything you'll need here
        $mockDoctrine = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')
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
