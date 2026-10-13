<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DecoratedEntityManagersPass;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestEntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DecoratedEntityManagersPassTest extends TestCase
{
    public function testDecoratedEntityManagerIsMadePublicAndPassedToTheRegistry(): void
    {
        $container = $this->createContainer();
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');

        $this->process($container);

        $this->assertTrue($container->getDefinition('app.decorator.inner')->isPublic());
        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.decorator.inner'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testInnerIdIsReadFromTheDecoration(): void
    {
        $container = $this->createContainer();
        $container->register('app.decorator', TestEntityManagerDecorator::class)
            ->setDecoratedService('doctrine.orm.default_entity_manager', 'app.original_entity_manager')
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        $this->assertTrue($container->getDefinition('app.original_entity_manager')->isPublic());
        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.original_entity_manager'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testInnermostEntityManagerIsPassedWhenDecoratedTwice(): void
    {
        $container = $this->createContainer();
        $this->decorate($container, 'app.inner_decorator', 'doctrine.orm.default_entity_manager', 10);
        $this->decorate($container, 'app.outer_decorator', 'doctrine.orm.default_entity_manager');

        $this->process($container);

        $this->assertSame(EntityManager::class, $container->getDefinition('app.inner_decorator.inner')->getClass());
        $this->assertTrue($container->getDefinition('app.inner_decorator.inner')->isPublic());
        $this->assertFalse($container->getDefinition('app.inner_decorator')->isPublic());
        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.inner_decorator.inner'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testInnermostEntityManagerIsPassedWhenItsDecoratorIsDecorated(): void
    {
        $container = $this->createContainer();
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');
        $this->decorate($container, 'app.decorator_decorator', 'app.decorator');

        $this->process($container);

        $this->assertSame(EntityManager::class, $container->getDefinition('app.decorator.inner')->getClass());
        $this->assertTrue($container->getDefinition('app.decorator.inner')->isPublic());
        $this->assertFalse($container->getDefinition('app.decorator_decorator.inner')->isPublic());
        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.decorator.inner'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testInnermostEntityManagerIsPassedWhenItsInnerServiceIsDecorated(): void
    {
        $container = $this->createContainer();
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');
        $this->decorate($container, 'app.inner_decorator', 'app.decorator.inner');

        $this->process($container);

        $this->assertSame(EntityManager::class, $container->getDefinition('app.inner_decorator.inner')->getClass());
        $this->assertTrue($container->getDefinition('app.inner_decorator.inner')->isPublic());
        $this->assertFalse($container->getDefinition('app.inner_decorator')->isPublic());
        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.inner_decorator.inner'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testEntityManagerSetAsAnInstanceIsIgnored(): void
    {
        $container = $this->createContainer();
        $container->set('doctrine.orm.other_entity_manager', new stdClass());
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');

        $this->process($container);

        $this->assertSame(
            ['doctrine.orm.default_entity_manager' => 'app.decorator.inner'],
            $container->getDefinition('doctrine')->getArgument(5),
        );
    }

    public function testOtherDecoratedServicesAreIgnored(): void
    {
        $container = $this->createContainer();
        $container->register('app.service', stdClass::class)->setPublic(true);
        $container->register('app.decorator', stdClass::class)
            ->setDecoratedService('app.service')
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        $this->assertFalse($container->getDefinition('app.decorator.inner')->isPublic());
        $this->assertCount(5, $container->getDefinition('doctrine')->getArguments());
    }

    public function testNothingIsDoneWithoutEntityManagers(): void
    {
        $container = $this->createContainer();
        $container->getParameterBag()->remove('doctrine.entity_managers');
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');

        $this->process($container);

        $this->assertFalse($container->getDefinition('app.decorator.inner')->isPublic());
        $this->assertCount(5, $container->getDefinition('doctrine')->getArguments());
    }

    public function testNothingIsDoneWithoutRegistry(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition('doctrine');
        $this->decorate($container, 'app.decorator', 'doctrine.orm.default_entity_manager');

        $this->process($container);

        $this->assertFalse($container->getDefinition('app.decorator.inner')->isPublic());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.entity_managers', [
            'default' => 'doctrine.orm.default_entity_manager',
            'other' => 'doctrine.orm.other_entity_manager',
        ]);

        $container->register('doctrine', Registry::class)
            ->setPublic(true)
            ->setArguments([new Reference('service_container'), [], '%doctrine.entity_managers%', 'default', 'default']);

        $container->register('doctrine.orm.default_entity_manager', EntityManager::class)->setPublic(true);
        $container->register('doctrine.orm.other_entity_manager', EntityManager::class)->setPublic(true);

        return $container;
    }

    private function decorate(ContainerBuilder $container, string $id, string $decoratedId, int $priority = 0): void
    {
        $container->register($id, TestEntityManagerDecorator::class)
            ->setDecoratedService($decoratedId, null, $priority)
            ->setArguments([new Reference('.inner')]);
    }

    private function process(ContainerBuilder $container): void
    {
        (new DecoratorServicePass())->process($container);
        (new DecoratedEntityManagersPass())->process($container);
    }
}
