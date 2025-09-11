<?php

declare(strict_types=1);

namespace Command;

use Doctrine\Bundle\DoctrineBundle\Command\DebugEntityListenersDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BarListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BazListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\FooListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Polyfill\SymfonyApp;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function interface_exists;

class DebugEntityListenersDoctrineCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testExecute(): void
    {
        $command = new DebugEntityListenersDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), 'entity' => self::class],
        );

        self::assertSame(<<<'TXT'

Entity listeners for Command\DebugEntityListenersDoctrineCommandTest
====================================================================

"postPersists" event
--------------------

 ------- ----------------------------------------------------------------------------------- 
  Order   Listener                                                                           
 ------- ----------------------------------------------------------------------------------- 
  #1      Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BazListener::postPersists()  
 ------- ----------------------------------------------------------------------------------- 

"preUpdate" event
-----------------

 ------- -------------------------------------------------------------------------------- 
  Order   Listener                                                                        
 ------- -------------------------------------------------------------------------------- 
  #1      Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\FooListener::preUpdate()  
  #2      Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BarListener::__invoke()   
 ------- -------------------------------------------------------------------------------- 


TXT
, $commandTester->getDisplay(true));
    }

    public function testExecuteWithEvent(): void
    {
        $command = new DebugEntityListenersDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), 'entity' => self::class, 'event' => 'postPersists'],
        );

        self::assertSame(<<<'TXT'

Entity listeners for Command\DebugEntityListenersDoctrineCommandTest
====================================================================

"postPersists" event
--------------------

 ------- ----------------------------------------------------------------------------------- 
  Order   Listener                                                                           
 ------- ----------------------------------------------------------------------------------- 
  #1      Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BazListener::postPersists()  
 ------- ----------------------------------------------------------------------------------- 


TXT
, $commandTester->getDisplay(true));
    }

    public function testExecuteWithMissingEvent(): void
    {
        $command = new DebugEntityListenersDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), 'entity' => self::class, 'event' => 'preRemove'],
        );

        self::assertSame(<<<'TXT'

Entity listeners for Command\DebugEntityListenersDoctrineCommandTest
====================================================================

"preRemove" event
-----------------

 No listeners are configured for this event.

TXT
, $commandTester->getDisplay(true));
    }

    /** @return MockObject&ManagerRegistry */
    private function getMockManagerRegistry(): MockObject
    {
        $mappingDriverMock = $this->createMock(MappingDriver::class);
        $mappingDriverMock->method('getAllClassNames')->willReturn([self::class]);

        $config = new Configuration();
        $config->setMetadataDriverImpl($mappingDriverMock);

        $classMetadata = new ClassMetadata(self::class);
        $classMetadata->addEntityListener('preUpdate', FooListener::class, 'preUpdate');
        $classMetadata->addEntityListener('preUpdate', BarListener::class, '__invoke');
        $classMetadata->addEntityListener('postPersists', BazListener::class, 'postPersists');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('getConfiguration')->willReturn($config);
        $emMock->method('getClassMetadata')->willReturn($classMetadata);

        $doctrineMock = $this->createMock(ManagerRegistry::class);
        $doctrineMock->method('getManagerNames')->willReturn(['default']);
        $doctrineMock->method('getManager')->willReturn($emMock);
        $doctrineMock->method('getManagerForClass')->willReturn($emMock);

        return $doctrineMock;
    }
}
