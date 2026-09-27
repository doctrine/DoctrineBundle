<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\PropertyInfo\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadata;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;
use stdClass;

class AliasClassMetadataFactory extends ClassMetadataFactory
{
    /** @return ClassMetadata<object> */
    public function getMetadataFor(string $className): ClassMetadata
    {
        return parent::getMetadataFor($className === stdClass::class ? TestDefaultRepoEntity::class : $className);
    }
}
