<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory as BaseClassMetadataFactory;

use function assert;

class ClassMetadataFactory extends BaseClassMetadataFactory
{
    /**
     * {@inheritDoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents): void
    {
        parent::doLoadMetadata($class, $parent, $rootEntityFound, $nonSuperclassParents);

        $customGeneratorDefinition = $class->customGeneratorDefinition;

        $generator = $customGeneratorDefinition['instance'] ?? null;
        if (! $generator) {
            return;
        }

        if (isset($customGeneratorDefinition['method'], $customGeneratorDefinition['arguments'])) {
            $generator = $generator->{$customGeneratorDefinition['method']}(...$customGeneratorDefinition['arguments']);
            assert($generator instanceof AbstractIdGenerator);
        }

        $class->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_CUSTOM);
        $class->setIdGenerator($generator);
        unset($customGeneratorDefinition['instance'], $customGeneratorDefinition['id'], $customGeneratorDefinition['method'], $customGeneratorDefinition['arguments']);
        $class->setCustomGeneratorDefinition($customGeneratorDefinition);
    }
}
