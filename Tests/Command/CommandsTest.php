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

class CommandsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getCommandClasses
     */
    public function testConfiguration($class)
    {
        $command = new $class;
        $options = $command->getDefinition()->getOptions();

        $command->setApplication($this->getApplicationMock());

        if (isset($options['em'])) {
            $this->assertNull($options['em']->getDefault());
            $this->assertContains('(default: "em3")', $command->asText());
            $this->assertContains('Available entity managers: em1, em2, em3', $command->getHelp());
            $this->assertNull($options['em']->getDefault());
        }

        if (isset($options['connection'])) {
            $this->assertNull($options['connection']->getDefault());
            $this->assertContains('(default: "c2")', $command->asText());
            $this->assertContains('Available connections: c1, c2, c3', $command->getHelp());
            $this->assertNull($options['connection']->getDefault());
        }

        $command->setApplication(null);

        if (isset($options['em'])) {
            $this->assertNull($options['em']->getDefault());
            $this->assertNotContains('(default: "em3")', $command->asText());
        }

        if (isset($options['connection'])) {
            $this->assertNull($options['connection']->getDefault());
            $this->assertNotContains('(default: "c2")', $command->asText());
        }
    }

    public function getCommandClasses()
    {
        $classes = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../Command', \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach (new \RegexIterator($iterator, '#.+Command\.php$#') as $file) {
            $class = 'Doctrine\Bundle\DoctrineBundle\Command\\'.trim(strtr($iterator->getSubPath().'\\'.$file->getBasename('.php'), '/', '\\'), '\\');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isSubclassOf('Symfony\Component\Console\Command\Command') && $reflection->isInstantiable()) {
                $classes[] = array($class);
            }

        }

        return $classes;
    }

    protected function getApplicationMock()
    {
        $doctrine = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $doctrine->expects($this->any())->method('getDefaultConnectionName')->will($this->returnValue('c2'));
        $doctrine->expects($this->any())->method('getConnectionNames')->will($this->returnValue(array_flip(array('c1', 'c2', 'c3'))));
        $doctrine->expects($this->any())->method('getDefaultManagerName')->will($this->returnValue('em3'));
        $doctrine->expects($this->any())->method('getManagerNames')->will($this->returnValue(array_flip(array('em1', 'em2', 'em3'))));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())->method('get')->with('doctrine')->will($this->returnValue($doctrine));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel->expects($this->any())->method('getContainer')->will($this->returnValue($container));

        $application = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Console\Application')->disableOriginalConstructor()->getMock();
        $application->expects($this->any())->method('getKernel')->will($this->returnValue($kernel));
        $application->expects($this->any())->method('getHelperSet')->will($this->returnValue($this->getMock('Symfony\Component\Console\Helper\HelperSet')));
        $application->expects($this->any())->method('getDefinition')->will($this->returnValue(new \Symfony\Component\Console\Input\InputDefinition()));

        return $application;
    }
}
