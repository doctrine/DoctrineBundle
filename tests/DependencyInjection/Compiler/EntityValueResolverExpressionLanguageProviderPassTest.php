<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityValueResolverExpressionLanguageProviderPass;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestExpressionLanguageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class EntityValueResolverExpressionLanguageProviderPassTest extends TestCase
{
    public function testTaggedProvidersAreRegistered(): void
    {
        $container = $this->createContainer();
        $container->register('app.expression_language_provider', TestExpressionLanguageProvider::class)
            ->addTag(EntityValueResolverExpressionLanguageProviderPass::EXPRESSION_LANGUAGE_PROVIDER_TAG);
        $container->register('app.other_expression_language_provider', TestExpressionLanguageProvider::class)
            ->addTag(EntityValueResolverExpressionLanguageProviderPass::EXPRESSION_LANGUAGE_PROVIDER_TAG);

        (new EntityValueResolverExpressionLanguageProviderPass())->process($container);

        $this->assertEquals(
            [
                ['registerProvider', [new Reference('app.expression_language_provider')]],
                ['registerProvider', [new Reference('app.other_expression_language_provider')]],
            ],
            $container->getDefinition('doctrine.orm.entity_value_resolver.expression_language')->getMethodCalls(),
        );
    }

    public function testUntaggedProvidersAreNotRegistered(): void
    {
        $container = $this->createContainer();
        $container->register('app.expression_language_provider', TestExpressionLanguageProvider::class);

        (new EntityValueResolverExpressionLanguageProviderPass())->process($container);

        $this->assertSame(
            [],
            $container->getDefinition('doctrine.orm.entity_value_resolver.expression_language')->getMethodCalls(),
        );
    }

    public function testTheSecurityProviderIsRegisteredWhenSecurityBundleIsInUse(): void
    {
        $container = $this->createContainer();
        $container->register('security.expression_language_provider', TestExpressionLanguageProvider::class);
        $container->register('app.expression_language_provider', TestExpressionLanguageProvider::class)
            ->addTag(EntityValueResolverExpressionLanguageProviderPass::EXPRESSION_LANGUAGE_PROVIDER_TAG);

        (new EntityValueResolverExpressionLanguageProviderPass())->process($container);

        $this->assertEquals(
            [
                ['registerProvider', [new Reference('security.expression_language_provider')]],
                ['registerProvider', [new Reference('app.expression_language_provider')]],
            ],
            $container->getDefinition('doctrine.orm.entity_value_resolver.expression_language')->getMethodCalls(),
        );
    }

    public function testNothingIsRegisteredWithoutSecurityBundle(): void
    {
        $container = $this->createContainer();

        (new EntityValueResolverExpressionLanguageProviderPass())->process($container);

        $this->assertSame(
            [],
            $container->getDefinition('doctrine.orm.entity_value_resolver.expression_language')->getMethodCalls(),
        );
    }

    public function testPassIsSkippedWithoutExpressionLanguage(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.expression_language_provider', TestExpressionLanguageProvider::class)
            ->addTag(EntityValueResolverExpressionLanguageProviderPass::EXPRESSION_LANGUAGE_PROVIDER_TAG);

        (new EntityValueResolverExpressionLanguageProviderPass())->process($container);

        $this->assertFalse($container->hasDefinition('doctrine.orm.entity_value_resolver.expression_language'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.entity_value_resolver.expression_language', ExpressionLanguage::class);

        return $container;
    }
}
