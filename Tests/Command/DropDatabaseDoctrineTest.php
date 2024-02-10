<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\Persistence\ManagerRegistry;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

use function array_merge;
use function sprintf;
use function sys_get_temp_dir;

/** @psalm-import-type Params from DriverManager */
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

        /** @psalm-suppress InvalidArgument Need to be compatible with DBAL < 4, which still has `$params['url']` */
        $container = $this->getMockContainer($connectionName, $params);

        $application = new Application();
        $application->add(new DropDatabaseDoctrineCommand($container->get('doctrine')));

        $command = $application->find('doctrine:database:drop');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array_merge(['command' => $command->getName()], $options),
        );

        $this->assertStringContainsString(
            sprintf(
                'Dropped database %s for connection named %s',
                sys_get_temp_dir() . '/' . $dbName,
                $connectionName,
            ),
            $commandTester->getDisplay(),
        );
    }

    /**
     * @param array<string, bool> $options
     *
     * @dataProvider provideIncompatibleDriverOptions
     */
    public function testItThrowsWhenUsingIfExistsWithAnIncompatibleDriver(array $options): void
    {
        $this->expectException(DBALException::class);

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
            array_merge(['command' => $command->getName()]),
        );

        $this->assertStringContainsString(
            sprintf(
                'Would drop the database %s for connection named %s.',
                sys_get_temp_dir() . '/' . $dbName,
                $connectionName,
            ),
            $commandTester->getDisplay(),
        );
        $this->assertStringContainsString(
            'Please run the operation with --force to execute',
            $commandTester->getDisplay(),
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
     * @psalm-param Params $params
     *
     * @return MockObject&Container
     */
    private function getMockContainer(string $connectionName, array $params): MockObject
    {
        // Mock the container and everything you'll need here
        $mockDoctrine = $this->getMockBuilder(ManagerRegistry::class)
            ->getMock();

        $mockDoctrine->expects($this->any())
            ->method('getDefaultConnectionName')
            ->withAnyParameters()
            ->willReturn($connectionName);

        $config = (new Configuration())->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('getConfiguration')->willReturn($config);

        $mockConnection->expects($this->any())
            ->method('getParams')
            ->withAnyParameters()
            ->willReturn($params);

        $mockDoctrine->expects($this->any())
            ->method('getConnection')
            ->withAnyParameters()
            ->willReturn($mockConnection);

        $mockContainer = $this->getMockBuilder(Container::class)
            ->onlyMethods(['get'])
            ->getMock();

        $mockContainer->expects($this->any())
            ->method('get')
            ->with('doctrine')
            ->willReturn($mockDoctrine);

        return $mockContainer;
    }
}
