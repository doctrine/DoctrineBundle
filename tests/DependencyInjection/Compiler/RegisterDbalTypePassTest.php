<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterDbalTypePass;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use function sprintf;

class RegisterDbalTypePassTest extends TestCase
{
    public function testTaggedTypeAreAdded(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register(BarType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'bar'])
            ->addTag('container.excluded');

        $container->compile();

        self::assertSame(['bar' => ['class' => BarType::class]], $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testServiceIdMustBeUsedAsTypeNameIfNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register('doctrine.dbal.type.bar')
            ->setClass(BarType::class)
            ->addTag('doctrine.dbal.type')
            ->addTag('container.excluded');

        $container->compile();

        self::assertSame(['doctrine.dbal.type.bar' => ['class' => BarType::class]], $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testTypeMustBeASubclassOfTheDbalBaseType(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterDbalTypePass());

        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->register(NotASubClassOfDbalBaseType::class)
            ->addTag('doctrine.dbal.type', ['type_name' => 'invalid_type'])
            ->addTag('container.excluded');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "%s" class must extends "%s".', NotASubClassOfDbalBaseType::class, Type::class));

        $container->compile();
    }
}

class BarType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'bar';
    }
}

class NotASubClassOfDbalBaseType
{
}
