<?php

namespace DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\MessengerPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerPassTest extends TestCase
{
    protected function setUp()
    {
        if (interface_exists(MessageBusInterface::class)) {
            return;
        }

        $this->markTestSkipped('Symfony Messenger component is not installed');
    }

    public function testRemovesDefinitionsWhenMessengerComponentIsDisabled()
    {
        $pass      = new MessengerPass();
        $container = new ContainerBuilder();
        $loader    = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../Resources/config'));
        $loader->load('messenger.xml');

        $pass->process($container);

        $this->assertFalse($container->hasDefinition('doctrine.orm.messenger.middleware_factory.transaction'));
        $this->assertFalse($container->hasDefinition('messenger.middleware.doctrine_transaction_middleware'));
    }

    public function testRemoveDefinitionsWhenHasAliasButNotMessengerComponent()
    {
        $pass      = new MessengerPass();
        $container = new ContainerBuilder();
        $loader    = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../Resources/config'));
        $loader->load('messenger.xml');

        $container->register('some_other_bus', stdClass::class);
        $container->setAlias('message_bus', 'some_other_bus');

        $pass->process($container);

        $this->assertFalse($container->hasDefinition('doctrine.orm.messenger.middleware_factory.transaction'));
        $this->assertFalse($container->hasDefinition('messenger.middleware.doctrine_transaction_middleware'));
    }

    public function testDoesNotRemoveDefinitionsWhenMessengerComponentIsEnabled()
    {
        $pass      = new MessengerPass();
        $container = new ContainerBuilder();
        $loader    = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../Resources/config'));
        $loader->load('messenger.xml');

        $container->register('messenger.bus.default', MessageBus::class);
        $container->setAlias('message_bus', 'messenger.bus.default');

        $pass->process($container);

        $this->assertTrue($container->hasDefinition('doctrine.orm.messenger.middleware_factory.transaction'));
        $this->assertTrue($container->hasDefinition('messenger.middleware.doctrine_transaction_middleware'));
    }
}
