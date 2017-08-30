<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Logging\SQLLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineDBALLoggerPass implements CompilerPassInterface
{
    private const TAG_NAME = 'doctrine.dbal.logger';
    private const BASE_CHAIN_NAME = 'doctrine.dbal.logger.chain';

    public function process(ContainerBuilder $container)
    {
        $serviceList = $container->findTaggedServiceIds(self::TAG_NAME, true);
        $serviceList = array_keys($serviceList);

        if (empty($serviceList)) {
            return;
        }

        foreach ($serviceList as $serviceId) {
            $this->ensureIsValidServiceDefinition($container, $serviceId);
        }

        $connectionList = $container->getParameter('doctrine.connections');

        foreach ($connectionList as $connectionName => $connectionId) {
            $configurationDefinition = $container->getDefinition($connectionId . '.configuration');
            $chainDefinition = $this->createChainDefinitionAttachedToConnection($container, $connectionName, $configurationDefinition);

            foreach ($serviceList as $serviceId) {
                $serviceReference = new Reference($serviceId);
                $chainDefinition->addMethodCall('addLogger', [$serviceReference]);
            }
        }
    }

    private function ensureIsValidServiceDefinition(ContainerBuilder $container, string $serviceId): void
    {
        $serviceDefinition = $container->getDefinition($serviceId);

        $serviceClass = $container->getParameterBag()->resolveValue(
            $serviceDefinition->getClass()
        );
        $reflectionClass = $container->getReflectionClass($serviceClass);

        if (!$reflectionClass) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" used for service "%s" cannot be found.',
                $serviceClass,
                $serviceId
            ));
        }

        if (!$reflectionClass->implementsInterface(SQLLogger::class)) {
            throw new InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must implement "%s".',
                $serviceId,
                self::TAG_NAME,
                SQLLogger::class
            ));
        }

        if ($reflectionClass->isAbstract()) {
            throw new InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" cannot be abstract.',
                $serviceId,
                self::TAG_NAME
            ));
        }
    }

    private function createChainDefinitionAttachedToConnection(
        ContainerBuilder $container,
        string $connectionName,
        $configurationDefinition
    ): Definition {
        $configuredLoggerReference = $this->getSQLLoggerFromConnection($configurationDefinition);

        if ($configuredLoggerReference === null) {
            return $this->createChainAndAttachToConnection($container, $connectionName, $configurationDefinition);
        }

        $configuredLoggerDefinition = $container->getDefinition((string)$configuredLoggerReference);

        if ($this->isChainLogger($configuredLoggerDefinition)) {
            return $configuredLoggerDefinition;
        }

        $chainDefinition = $this->createChainAndAttachToConnection($container, $connectionName, $configurationDefinition);
        $chainDefinition->addMethodCall('addLogger', [$configuredLoggerReference]);

        return $chainDefinition;
    }

    private function getSQLLoggerFromConnection(Definition $configurationDefinition): ?Reference
    {
        $methodCallList = $configurationDefinition->getMethodCalls();

        foreach ($methodCallList as $methodCall) {
            if ($methodCall[0] !== 'setSQLLogger') {
                continue;
            }

            return $methodCall[1][0];
        }

        return null;
    }

    private function isChainLogger(Definition $serviceDefinition): bool
    {
        $methodCallList = $serviceDefinition->getMethodCalls();

        foreach ($methodCallList as $methodCall) {
            if ($methodCall[0] === 'addLogger') {
                return true;
            }
        }

        return false;
    }

    private function createChainAndAttachToConnection(
        ContainerBuilder $container,
        string $connectionName,
        Definition $configurationDefinition
    ): Definition {
        $chainId = self::BASE_CHAIN_NAME . '.' . $connectionName;

        $chainReference = new Reference($chainId);
        $chainDefinition = new Definition(self::BASE_CHAIN_NAME);

        $container->setDefinition($chainId, $chainDefinition);

        $configurationDefinition->removeMethodCall('setSQLLogger');
        $configurationDefinition->addMethodCall('setSQLLogger', [$chainReference]);

        return $chainDefinition;
    }
}
