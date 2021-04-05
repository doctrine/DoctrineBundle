<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory as BaseClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use function assert;

class ClassMetadataFactory extends BaseClassMetadataFactory
{
    /**
     * {@inheritDoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents): void
    {
        parent::doLoadMetadata($class, $parent, $rootEntityFound, $nonSuperclassParents);

        if (! $class instanceof ClassMetadataInfo) {
            return;
        }

        $customGeneratorDefinition = $class->customGeneratorDefinition;

        if (! isset($customGeneratorDefinition['instance'])) {
            return;
        }

        assert($customGeneratorDefinition['instance'] instanceof AbstractIdGenerator);

        $class->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_CUSTOM);
        $class->setIdGenerator($customGeneratorDefinition['instance']);
        unset($customGeneratorDefinition['instance']);
        $class->setCustomGeneratorDefinition($customGeneratorDefinition);
    }
}
