<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver;

use Doctrine\Bundle\DoctrineBundle\ArgumentResolver\EntityValueResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\Entity;
use Doctrine\DBAL\Types\ConversionException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_array;
use function method_exists;

class EntityValueResolverTest extends TestCase
{
    public function testApplyWithNoIdAndData()
    {
        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $this->expectException(LogicException::class);

        $request  = new Request();
        $argument = $this->createArgument(null, new Entity());

        $converter->resolve($request, $argument);
    }

    public function testApplyWithNoIdAndDataOptional()
    {
        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request  = new Request();
        $argument = $this->createArgument(null, new Entity(), 'arg', true);

        $ret = $converter->resolve($request, $argument);

        $this->assertNull($this->toArray($ret)[0]);
    }

    public function testApplyWithStripNulls()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', null);
        $argument = $this->createArgument('stdClass', new Entity(null, null, null, ['arg' => 'arg'], [], true), 'arg', true);

        $classMetadata = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadata')->getMock();
        $manager       = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $manager->expects($this->never())
            ->method('getRepository');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($manager);

        $classMetadata->expects($this->once())
            ->method('hasField')
            ->with($this->equalTo('arg'))
            ->willReturn(true);

        $ret = $converter->resolve($request, $argument);

        $this->assertNull($this->toArray($ret)[0]);
    }

    /**
     * @param string|int $id
     *
     * @dataProvider idsProvider
     */
    public function testApplyWithId($id)
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', $id);

        $argument = $this->createArgument('stdClass', new Entity(null, null, null, [], [], false, 'id'));

        $manager          = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();
        $registry->expects($this->once())
              ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $objectRepository->expects($this->once())
                      ->method('find')
                      ->with($this->equalTo($id))
                      ->willReturn($object = new stdClass());

        $ret = $converter->resolve($request, $argument);

        $this->assertSame($object, $this->toArray($ret)[0]);
    }

    public function testApplyWithConversionFailedException()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $this->expectException(NotFoundHttpException::class);

        $request = new Request();
        $request->attributes->set('id', 'test');

        $argument = $this->createArgument('stdClass', new Entity(null, null, null, [], [], false, 'id'));

        $manager          = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();
        $registry->expects($this->once())
              ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $objectRepository->expects($this->once())
                      ->method('find')
                      ->with($this->equalTo('test'))
                      ->will($this->throwException(new ConversionException()));

        $converter->resolve($request, $argument);
    }

    public function testUsedProperIdentifier()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', 1);
        $request->attributes->set('entity_id', null);
        $request->attributes->set('arg', null);

        $argument = $this->createArgument('stdClass', new Entity(null, null, null, [], [], false, 'entity_id'), 'arg', true);

        $ret = $converter->resolve($request, $argument);

        $this->assertNull($this->toArray($ret)[0]);
    }

    /** @return array{0: int|string}[] */
    public function idsProvider()
    {
        return [
            [1],
            [0],
            ['foo'],
        ];
    }

    public function testApplyGuessOptional()
    {
        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', null);

        $argument = $this->createArgument('stdClass', new Entity(), 'arg', true);

        $classMetadata = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadata')->getMock();
        $manager       = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();
        $registry->expects($this->once())
              ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->never())->method('getRepository');

        $objectRepository->expects($this->never())->method('find');
        $objectRepository->expects($this->never())->method('findOneBy');

        $ret = $converter->resolve($request, $argument);

        $this->assertNull($this->toArray($ret)[0]);
    }

    public function testApplyWithMappingAndExclude()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('foo', 1);
        $request->attributes->set('bar', 2);

        $argument = $this->createArgument(
            'stdClass',
            new Entity(null, null, null, ['foo' => 'Foo'], ['bar'])
        );

        $manager    = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $metadata   = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadata')->getMock();
        $repository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();

        $registry->expects($this->once())
                ->method('getManagerForClass')
                ->with('stdClass')
                ->willReturn($manager);

        $manager->expects($this->once())
                ->method('getClassMetadata')
                ->with('stdClass')
                ->willReturn($metadata);
        $manager->expects($this->once())
                ->method('getRepository')
                ->with('stdClass')
                ->willReturn($repository);

        $metadata->expects($this->once())
                 ->method('hasField')
                 ->with($this->equalTo('Foo'))
                 ->willReturn(true);

        $repository->expects($this->once())
                      ->method('findOneBy')
                      ->with($this->equalTo(['Foo' => 1]))
                      ->willReturn($object = new stdClass());

        $ret = $converter->resolve($request, $argument);

        $this->assertSame($object, $this->toArray($ret)[0]);
    }

    public function testIgnoreMappingWhenAutoMappingDisabled()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry, null, ['auto_mapping' => false]);

        $request = new Request();
        $request->attributes->set('foo', 1);

        $argument = $this->createArgument(
            'stdClass',
            new Entity()
        );

        $metadata = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadata')->getMock();

        $registry->expects($this->never())
                ->method('getManagerForClass');

        $metadata->expects($this->never())
                 ->method('hasField');

        $this->expectException(LogicException::class);

        $ret = $converter->resolve($request, $argument);

        $this->assertCount(0, $this->toArray($ret));
    }

    public function testSupports()
    {
        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $argument        = $this->createArgument('stdClass', new Entity());
        $metadataFactory = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadataFactory')->getMock();
        $metadataFactory->expects($this->once())
                        ->method('isTransient')
                        ->with($this->equalTo('stdClass'))
                        ->willReturn(false);

        $objectManager = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectManager->expects($this->once())
                      ->method('getMetadataFactory')
                      ->willReturn($metadataFactory);

        $registry->expects($this->any())
                    ->method('getManagerNames')
                    ->willReturn(['default']);

        $registry->expects($this->once())
                      ->method('getManagerForClass')
                      ->with('stdClass')
                      ->willReturn($objectManager);

        $ret = $converter->supports(new Request(), $argument);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithConfiguredEntityManager()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $argument        = $this->createArgument('stdClass', new Entity(null, 'foo'));
        $metadataFactory = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadataFactory')->getMock();
        $metadataFactory->expects($this->once())
                        ->method('isTransient')
                        ->with($this->equalTo('stdClass'))
                        ->willReturn(false);

        $objectManager = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectManager->expects($this->once())
                      ->method('getMetadataFactory')
                      ->willReturn($metadataFactory);

        $registry->expects($this->once())
                    ->method('getManagerNames')
                    ->willReturn(['default']);

        $registry->expects($this->once())
                      ->method('getManager')
                      ->with('foo')
                      ->willReturn($objectManager);

        $ret = $converter->supports(new Request(), $argument);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithDifferentConfiguration()
    {
        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $argument = $this->createArgument('DateTime');

        $objectManager = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectManager->expects($this->never())
                      ->method('getMetadataFactory');

        $registry->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default']);

        $registry->expects($this->never())
                      ->method('getManager');

        $ret = $converter->supports(new Request(), $argument);

        $this->assertFalse($ret, 'Should not be supported');
    }

    public function testExceptionWithExpressionIfNoLanguageAvailable()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry);

        $this->expectException(LogicException::class);

        $request  = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new Entity(null, null, 'repository.find(id)'),
            'arg1'
        );

        $converter->resolve($request, $argument);
    }

    public function testExpressionFailureReturns404()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        $language = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ExpressionLanguage')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry, $language);

        $this->expectException(NotFoundHttpException::class);

        $request  = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new Entity(null, null, 'repository.someMethod()'),
            'arg1'
        );

        $objectManager    = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->willReturn(null);

        $converter->resolve($request, $argument);
    }

    public function testExpressionMapsToArgument()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        $language = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ExpressionLanguage')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry, $language);

        $request = new Request();
        $request->attributes->set('id', 5);
        $argument = $this->createArgument(
            'stdClass',
            new Entity(null, null, 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $objectManager    = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->with('repository.findOneByCustomMethod(id)', [
                'repository' => $objectRepository,
                'id' => 5,
            ])
            ->willReturn('new_mapped_value');

        $ret = $converter->resolve($request, $argument);
        $this->assertEquals('new_mapped_value', $this->toArray($ret)[0]);
    }

    public function testExpressionSyntaxErrorThrowsException()
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            $this->markTestSkipped('Options are only configurable with symfony 5.2');
        }

        $registry = $this->getMockBuilder('Doctrine\Persistence\ManagerRegistry')->getMock();
        $language = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ExpressionLanguage')->getMock();
        /** @psalm-suppress InvalidArgument */
        $converter = new EntityValueResolver($registry, $language);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('syntax error message around position 10');

        $request  = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new Entity(null, null, 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $objectManager    = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')->getMock();
        $objectRepository = $this->getMockBuilder('Doctrine\Persistence\ObjectRepository')->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->will($this->throwException(new SyntaxError('syntax error message', 10)));

        $converter->resolve($request, $argument);
    }

    private function createArgument(?string $class = null, ?Entity $entity = null, string $name = 'arg', bool $isNullable = false): ArgumentMetadata
    {
        if (! method_exists(ArgumentMetadata::class, 'getAttributes')) {
            return new ArgumentMetadata($name, $class ?? stdClass::class, false, false, null, $isNullable);
        }

        /** @psalm-suppress TooManyArguments */
        return new ArgumentMetadata($name, $class ?? stdClass::class, false, false, null, $isNullable, $entity ? [$entity] : []);
    }

    /**
     * @param object[] $it
     *
     * @return object[]
     */
    private function toArray(iterable $it): array
    {
        if (is_array($it)) {
            return $it;
        }

        $ret = [];
        foreach ($it as $k => $v) {
            $ret[$k] = $v;
        }

        return $ret;
    }
}
