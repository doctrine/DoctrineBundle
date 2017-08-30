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
    private const LOGGER_INTERFACE = SQLLogger::class;
    private const BASE_CHAIN_NAME = 'doctrine.dbal.logger.chain';

    public function process(ContainerBuilder $container)
    {
        $serviceList = $container->findTaggedServiceIds(self::TAG_NAME, true);
        $serviceList = array_keys($serviceList);

        foreach ($serviceList as $serviceId) {
            $this->ensureIsValidServiceDefinition($container, $serviceId);
            $this->attachServiceToAllConnections($container, $serviceId);
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

        if (!$reflectionClass->implementsInterface(self::LOGGER_INTERFACE)) {
            throw new InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must implement "%s".',
                $serviceId,
                self::TAG_NAME,
                self::LOGGER_INTERFACE
            ));
        }

        if ($reflectionClass->isAbstract()) {
            throw new InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must not be abstract.',
                $serviceId,
                self::TAG_NAME
            ));
        }
    }

    private function attachServiceToAllConnections(ContainerBuilder $container, string $serviceId): void
    {
        $serviceReference = new Reference($serviceId);

        $connectionList = $container->getParameter('doctrine.connections');

        foreach ($connectionList as $connectionName => $connectionId) {
            $chainId = self::BASE_CHAIN_NAME . '.' . $connectionName;

            if (!$container->hasDefinition($chainId)) {
                $this->createEmptyChain($container, $chainId, $connectionId);
            }

            $chainDefinition = $container->getDefinition($chainId);
            $chainDefinition->addMethodCall('addLogger', array($serviceReference));
        }
    }

    private function createEmptyChain(ContainerBuilder $container, string $chainId, string $connectionId): void
    {
        $chainReference = new Reference($chainId);

        $container->setDefinition($chainId, new Definition(self::BASE_CHAIN_NAME));

        $configurationDefinition = $container->getDefinition($connectionId . '.configuration');
        $configurationDefinition->addMethodCall('setSQLLogger', array($chainReference));
    }
}
