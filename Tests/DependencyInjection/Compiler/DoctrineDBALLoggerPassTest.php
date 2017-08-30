<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\SQLLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineDBALLoggerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->loadContainer('my-logger', MySQLLogger::class);

        self::assertEquals(
            [
                [
                    'addLogger', [
                        new Reference('my-logger'),
                    ],
                ],
            ],
            $container->getDefinition('doctrine.dbal.logger.chain.tagged')->getMethodCalls()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The service "not-a-logger" tagged "doctrine.dbal.logger" must implement "Doctrine\DBAL\Logging\SQLLogger".
     */
    public function testProcessWithoutSQLLoger()
    {
        $this->loadContainer('not-a-logger', NotASQLLogger::class);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The service "abstract-logger" tagged "doctrine.dbal.logger" cannot be abstract.
     */
    public function testProcessWithAbstractLogger()
    {
        $this->loadContainer('abstract-logger', AbstractSQLLogger::class);
    }

    private function loadContainer(string $loggerName, string $loggerClass): ContainerBuilder
    {
        $configurationDefinition = new Definition(Configuration::class);

        $chainDefinition = new Definition(LoggerChain::class);

        $loggerDefinition = new Definition($loggerClass);
        $loggerDefinition->addTag('doctrine.dbal.logger');

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.connections', [
            'tagged' => 'doctrine.dbal.tagged_connection',
        ]);
        $container->setDefinition('doctrine.dbal.tagged_connection.configuration', $configurationDefinition);
        $container->setDefinition('doctrine.dbal.logger.chain', $chainDefinition);
        $container->setDefinition($loggerName, $loggerDefinition);

        $compilerPass = new DoctrineDBALLoggerPass;
        $compilerPass->process($container);

        return $container;
    }
}

abstract class AbstractSQLLogger implements SQLLogger
{
    public function startQuery($sql, array $params = null, array $types = null)
    {
        // no-op
    }

    public function stopQuery()
    {
        // no-op
    }
}

final class MySQLLogger extends AbstractSQLLogger {}

final class NotASQLLogger {}
