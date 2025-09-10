<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\AttachEntityListenersListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function class_exists;
use function interface_exists;

class EntityListenerPassTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    #[DataProvider('provideEvents')]
    public function testEntityListenersAreRegistered(string|null $event, string|null $method, string|null $expectedMethod): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new EntityListenerPass());

        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_entity_manager', EntityManager::class);
        $container->register('doctrine.orm.default_entity_listener_resolver', ContainerEntityListenerResolver::class);
        $container->register('doctrine.orm.default_listeners.attach_entity_listeners', AttachEntityListenersListener::class)
            ->setPublic(true);

        $tagAttributes = [
            'entity' => stdClass::class,
            'event' => $event,
            'method' => $method,
        ];
        $container->register(TestListener::class)->addTag('doctrine.orm.entity_listener', $tagAttributes);

        $container->compile();

        $definition = $container->getDefinition('doctrine.orm.default_listeners.attach_entity_listeners');

        $methodCalls = $definition->getMethodCalls();
        self::assertSame('addEntityListener', $methodCalls[0][0]);
        self::assertSame(stdClass::class, $methodCalls[0][1][0]);
        self::assertSame(TestListener::class, $methodCalls[0][1][1]);
        self::assertSame($event, $methodCalls[0][1][2]);
        self::assertSame($expectedMethod, $methodCalls[0][1][3] ?? null);
    }

    /** @return iterable<array{0: ?string, 1: ?string, 2: ?string}> */
    public static function provideEvents(): iterable
    {
        if (! class_exists(Events::class)) {
            // If ORM is not available, return fake data to make the data provider valid
            yield 'Without ORM' => [null, null, null];

            return;
        }

        yield 'With event and matching method' => [Events::prePersist, null, null];
        yield 'Without event' => [null, null, null];
        yield 'With event and custom method' => [Events::postLoad, 'postLoadHandler', 'postLoadHandler'];
        yield 'With event and no matching method' => [Events::postLoad, null, '__invoke'];
    }

    public function testMultipleAttributesOnSameClassKeepTheCorrectOrder(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new EntityListenerPass());

        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_entity_manager', EntityManager::class);
        $container->register('doctrine.orm.default_entity_listener_resolver', ContainerEntityListenerResolver::class);
        $container->register('doctrine.orm.default_listeners.attach_entity_listeners', AttachEntityListenersListener::class)
            ->setPublic(true);

        $container->register(TestListener::class)
            ->addTag('doctrine.orm.entity_listener', ['entity' => stdClass::class, 'event' => Events::prePersist])
            ->addTag('doctrine.orm.entity_listener', ['entity' => stdClass::class, 'event' => Events::postPersist]);
        $container->register(TestListener2::class)
            ->addTag(
                'doctrine.orm.entity_listener',
                ['entity' => stdClass::class, 'event' => Events::prePersist, 'priority' => 1],
            )
            ->addTag(
                'doctrine.orm.entity_listener',
                ['entity' => stdClass::class, 'event' => Events::postPersist, 'priority' => -1],
            );

        $container->compile();

        $this->assertSame(
            [
                ['addEntityListener', ['stdClass', TestListener2::class, 'prePersist']],
                ['addEntityListener', ['stdClass', TestListener::class, 'prePersist']],
                ['addEntityListener', ['stdClass', TestListener::class, 'postPersist']],
                ['addEntityListener', ['stdClass', TestListener2::class, 'postPersist']],
            ],
            $container->getDefinition('doctrine.orm.default_listeners.attach_entity_listeners')->getMethodCalls(),
        );
    }
}

class TestListener
{
    public function prePersist(): void
    {
    }

    public function postPersist(): void
    {
    }

    public function postLoadHandler(): void
    {
    }

    public function __invoke(): void
    {
    }
}


class TestListener2
{
    public function prePersist(): void
    {
    }

    public function postPersist(): void
    {
    }
}
