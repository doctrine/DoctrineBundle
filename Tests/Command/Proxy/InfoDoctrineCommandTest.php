<?php

namespace Command\Proxy;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InfoDoctrineCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();

        $application = new Application($kernel);
        $command     = $application->find('doctrine:mapping:info');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString(
            'Found 3 mapped entities',
            $commandTester->getDisplay()
        );
    }
}
