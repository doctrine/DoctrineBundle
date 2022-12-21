<?php

namespace Command\Proxy;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function class_exists;
use function interface_exists;

use const PHP_VERSION_ID;

class InfoDoctrineCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (PHP_VERSION_ID < 80000 && ! class_exists(AnnotationReader::class)) {
            self::markTestSkipped('This test requires Annotations when run on PHP 7');
        }

        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

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
