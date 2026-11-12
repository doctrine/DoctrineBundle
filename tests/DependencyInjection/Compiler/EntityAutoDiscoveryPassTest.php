<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityAutoDiscoveryPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function method_exists;

class EntityAutoDiscoveryPassTest extends TestCase
{
    public function testPopulatesAutoDiscoverLocatorsWithTaggedEntityClasses(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (! method_exists(ContainerBuilder::class, 'findTaggedResourceIds')) {
            self::markTestSkipped('symfony/dependency-injection 8.2 is required.');
        }

        $container = new ContainerBuilder();

        $locator = new Definition('ClassNames', [[]]);
        $locator->addTag(EntityAutoDiscoveryPass::TAG);
        $container->setDefinition('doctrine.orm.default_auto_discover_class_locator', $locator);

        $stub = new Definition('App\Entity\Book');
        $stub->addTag('container.excluded');
        $stub->addTag('doctrine.orm.entity');
        $container->setDefinition('App\Entity\Book', $stub);

        $stub = new Definition('App\Entity\AbstractContent');
        $stub->setAbstract(true);
        $stub->addTag('container.excluded');
        $stub->addTag('doctrine.orm.entity');
        $container->setDefinition('.abstract.App\Entity\AbstractContent', $stub);

        (new EntityAutoDiscoveryPass())->process($container);

        self::assertSame(
            ['App\Entity\Book', 'App\Entity\AbstractContent'],
            $container->getDefinition('doctrine.orm.default_auto_discover_class_locator')->getArgument(0),
        );
    }

    public function testIgnoresExcludedClassesWithoutTheEntityTag(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (! method_exists(ContainerBuilder::class, 'findTaggedResourceIds')) {
            self::markTestSkipped('symfony/dependency-injection 8.2 is required.');
        }

        $container = new ContainerBuilder();

        $locator = new Definition('ClassNames', [[]]);
        $locator->addTag(EntityAutoDiscoveryPass::TAG);
        $container->setDefinition('doctrine.orm.default_auto_discover_class_locator', $locator);

        $stub = new Definition('App\Entity\Legacy');
        $stub->setAbstract(true);
        $stub->addTag('container.excluded');
        $container->setDefinition('App\Entity\Legacy', $stub);

        (new EntityAutoDiscoveryPass())->process($container);

        self::assertSame(
            [],
            $container->getDefinition('doctrine.orm.default_auto_discover_class_locator')->getArgument(0),
        );
    }

    public function testNoOpWithoutAutoDiscoverLocators(): void
    {
        $container = new ContainerBuilder();

        $stub = new Definition('App\Entity\Book');
        $stub->setAbstract(true);
        $stub->addTag('container.excluded');
        $stub->addTag('doctrine.orm.entity');
        $container->setDefinition('App\Entity\Book', $stub);

        (new EntityAutoDiscoveryPass())->process($container);

        self::assertTrue($container->hasDefinition('App\Entity\Book'));
    }
}
