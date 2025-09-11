<?php

declare(strict_types=1);

namespace Command;

use Doctrine\Bundle\DoctrineBundle\Command\DebugEventManagerDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BarListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\BazListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Command\Fixtures\FooListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Polyfill\SymfonyApp;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function interface_exists;

class DebugEventManagerDoctrineCommandTest extends TestCase
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
        $command = new DebugEventManagerDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName()],
        );

        self::assertSame(<<<'TXT'

Event listeners for default entity manager
==========================================

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
        $command = new DebugEventManagerDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), 'event' => 'postPersists'],
        );

        self::assertSame(<<<'TXT'

Event listeners for default entity manager
==========================================

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
        $command = new DebugEventManagerDoctrineCommand($this->getMockManagerRegistry());

        $application = new SymfonyApp();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), 'event' => 'preRemove'],
        );

        self::assertSame(<<<'TXT'

Event listeners for default entity manager
==========================================

"preRemove" event
-----------------

 No listeners are configured for this event.

TXT
            , $commandTester->getDisplay(true));
    }

    /** @return MockObject&ManagerRegistry */
    private function getMockManagerRegistry(): MockObject
    {
        $eventManager = new EventManager();
        $eventManager->addEventListener('preUpdate', new FooListener());
        $eventManager->addEventListener('preUpdate', new BarListener());
        $eventManager->addEventListener('postPersists', new BazListener());

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('getEventManager')->willReturn($eventManager);

        $doctrineMock = $this->createMock(ManagerRegistry::class);
        $doctrineMock->method('getDefaultManagerName')->willReturn('default');
        $doctrineMock->method('getManager')->willReturn($emMock);

        return $doctrineMock;
    }
}
