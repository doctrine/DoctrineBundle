<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheSchemaSubscriberPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DbalSchemaFilterPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\MiddlewaresPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RemoveLoggingMiddlewarePass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RemoveProfilerControllerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterDatePointTypePass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterUidTypePass;
use Symfony\Bridge\Doctrine\DependencyInjection\Security\UserProvider\EntityFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function assert;
use function class_exists;
use function dirname;

/** @final */
class DoctrineBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->has('session.handler')) {
                    return;
                }

                $container->removeDefinition('doctrine.orm.listeners.pdo_session_handler_schema_listener');
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine'), PassConfig::TYPE_BEFORE_OPTIMIZATION);

        if ($container->hasExtension('security')) {
            $security = $container->getExtension('security');

            if ($security instanceof SecurityExtension) {
                $security->addUserProviderFactory(new EntityFactory('entity', 'doctrine.orm.security.user.provider'));
            }
        }

        $container->addCompilerPass(new DoctrineValidationPass('orm'));
        $container->addCompilerPass(new EntityListenerPass());
        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
        $container->addCompilerPass(new IdGeneratorPass());
        $container->addCompilerPass(new DbalSchemaFilterPass());
        $container->addCompilerPass(new CacheSchemaSubscriberPass(), PassConfig::TYPE_BEFORE_REMOVING, -10);
        $container->addCompilerPass(new RemoveProfilerControllerPass());
        $container->addCompilerPass(new RemoveLoggingMiddlewarePass());
        $container->addCompilerPass(new MiddlewaresPass());
        $container->addCompilerPass(new RegisterUidTypePass());

        if (! class_exists(RegisterDatePointTypePass::class)) {
            return;
        }

        $container->addCompilerPass(new RegisterDatePointTypePass());
    }

    public function shutdown(): void
    {
        // Clear all entity managers to clear references to entities for GC
        if ($this->container->hasParameter('doctrine.entity_managers')) {
            foreach ($this->container->getParameter('doctrine.entity_managers') as $id) {
                if (! $this->container->initialized($id)) {
                    continue;
                }

                $entityManager = $this->container->get($id);
                assert($entityManager instanceof EntityManagerInterface);
                $entityManager->clear();
            }
        }

        // Close all connections to avoid reaching too many connections in the process when booting again later (tests)
        if (! $this->container->hasParameter('doctrine.connections')) {
            return;
        }

        foreach ($this->container->getParameter('doctrine.connections') as $id) {
            if (! $this->container->initialized($id)) {
                continue;
            }

            $connection = $this->container->get($id);
            assert($connection instanceof Connection);
            $connection->close();
        }
    }

    public function registerCommands(Application $application): void
    {
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
