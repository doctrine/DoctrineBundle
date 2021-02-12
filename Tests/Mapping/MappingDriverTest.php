<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Mapping;

use Doctrine\Bundle\DoctrineBundle\Mapping\MappingDriver;
use Doctrine\Bundle\DoctrineBundle\Mapping\ServiceGeneratedValue;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class MappingDriverTest extends TestCase
{
    public function testServiceGeneratedValueAnnotation(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->with('id-generator')->willReturn(true);
        $container->expects($this->any())->method('get')->with('id-generator')->willReturn('the-id-generator');

        $annotDriver   = new AnnotationDriver(new AnnotationReader());
        $mappingDriver = new MappingDriver($annotDriver, $container);

        $class               = ServiceGeneratedValueAnnotated::class;
        $metadata            = new ClassMetadataInfo($class);
        $metadata->reflClass = new ReflectionClass($class);
        $mappingDriver->loadMetadataForClass($class, $metadata);

        $expected = [
            'instance' => 'the-id-generator',
            'id' => 'id-generator',
            'method' => 'configure',
            'arguments' => [123],
        ];
        $this->assertSame($expected, $metadata->customGeneratorDefinition);
    }

    /**
     * @requires PHP 8
     */
    public function testServiceGeneratedValueAttribute(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->with('id-generator')->willReturn(true);
        $container->expects($this->any())->method('get')->with('id-generator')->willReturn('the-id-generator');

        $annotDriver   = new AnnotationDriver(new AnnotationReader());
        $mappingDriver = new MappingDriver($annotDriver, $container);

        $class               = ServiceGeneratedValueAttributed::class;
        $metadata            = new ClassMetadataInfo($class);
        $metadata->reflClass = new ReflectionClass($class);
        $mappingDriver->loadMetadataForClass($class, $metadata);

        $expected = [
            'instance' => 'the-id-generator',
            'id' => 'id-generator',
            'method' => 'configure',
            'arguments' => [234],
        ];
        $this->assertSame($expected, $metadata->customGeneratorDefinition);
    }
}

/** @ORM\Entity() */
class ServiceGeneratedValueAnnotated
{
    /**
     * @ServiceGeneratedValue(id="id-generator", method="configure", arguments={123})
     *
     * @var int
     */
    public $id;
}

/** @ORM\Entity() */
class ServiceGeneratedValueAttributed
{
    /** @var int */
    #[ServiceGeneratedValue('id-generator', 'configure', 234)]
    public $id;
}
