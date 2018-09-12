<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Class for Symfony Messenger component integrations
 */
class MessengerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasAlias('message_bus') && is_subclass_of($container->findDefinition('message_bus')->getClass(), MessageBusInterface::class)) {
            return;
        }

        // Remove wired services if the Messenger component actually isn't enabled:
        $container->removeDefinition('doctrine.orm.messenger.middleware_factory.transaction');
        $container->removeDefinition('messenger.middleware.doctrine_transaction_middleware');
    }
}
