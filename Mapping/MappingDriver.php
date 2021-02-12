<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver as MappingDriverInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Psr\Container\ContainerInterface;

use const PHP_VERSION_ID;

class MappingDriver implements MappingDriverInterface
{
    /** @var MappingDriverInterface */
    private $driver;

    /** @var ContainerInterface */
    private $idGeneratorLocator;

    public function __construct(MappingDriverInterface $driver, ContainerInterface $idGeneratorLocator)
    {
        $this->driver             = $driver;
        $this->idGeneratorLocator = $idGeneratorLocator;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        return $this->driver->getAllClassNames();
    }

    /**
     * {@inheritDoc}
     */
    public function isTransient($className): bool
    {
        return $this->driver->isTransient($className);
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata): void
    {
        $this->driver->loadMetadataForClass($className, $metadata);

        if (
            $metadata->generatorType === ClassMetadataInfo::GENERATOR_TYPE_CUSTOM
            && isset($metadata->customGeneratorDefinition['class'])
            && $this->idGeneratorLocator->has($metadata->customGeneratorDefinition['class'])
        ) {
            $idGenerator = $this->idGeneratorLocator->get($metadata->customGeneratorDefinition['class']);
            $metadata->setCustomGeneratorDefinition(['instance' => $idGenerator] + $metadata->customGeneratorDefinition);
            $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);

            return;
        }

        if ($metadata->generatorType !== ClassMetadataInfo::GENERATOR_TYPE_NONE) {
            return;
        }

        $driver = $this->driver;
        if ($driver instanceof MappingDriverChain) {
            foreach ($driver->getDrivers() as $namespace => $driver) {
                if (strpos($className, $namespace) === 0) {
                    break;
                }
            }
        }

        if (! $driver instanceof AnnotationDriver) {
            return;
        }

        $annotation = null;
        foreach ($metadata->getReflectionClass()->getProperties() as $property) {
            if (PHP_VERSION_ID >= 80000) {
                $attributes = $property->getAttributes(ServiceGeneratedValue::class);
                if ($attributes) {
                    $annotation = $attributes[0]->newInstance();
                    break;
                }
            }

            $annotation = $driver->getReader()->getPropertyAnnotation($property, ServiceGeneratedValue::class);
            if ($annotation) {
                break;
            }
        }

        if (! $annotation instanceof ServiceGeneratedValue) {
            return;
        }

        $idGenerator = $this->idGeneratorLocator->get($annotation->id);
        $metadata->setCustomGeneratorDefinition(['instance' => $idGenerator] + (array) $annotation);
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
    }
}
