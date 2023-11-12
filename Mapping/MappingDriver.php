<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver as MappingDriverInterface;
use Psr\Container\ContainerInterface;

class MappingDriver implements MappingDriverInterface
{
    private MappingDriverInterface $driver;
    private ContainerInterface $idGeneratorLocator;

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
            ! $metadata instanceof OrmClassMetadata
            || $metadata->generatorType !== OrmClassMetadata::GENERATOR_TYPE_CUSTOM
            || ! isset($metadata->customGeneratorDefinition['class'])
            || ! $this->idGeneratorLocator->has($metadata->customGeneratorDefinition['class'])
        ) {
            return;
        }

        $idGenerator = $this->idGeneratorLocator->get($metadata->customGeneratorDefinition['class']);
        $metadata->setCustomGeneratorDefinition(['instance' => $idGenerator] + $metadata->customGeneratorDefinition);
        $metadata->setIdGeneratorType(OrmClassMetadata::GENERATOR_TYPE_NONE);
    }

    /**
     * Returns the inner driver
     */
    public function getDriver(): MappingDriverInterface
    {
        return $this->driver;
    }
}
