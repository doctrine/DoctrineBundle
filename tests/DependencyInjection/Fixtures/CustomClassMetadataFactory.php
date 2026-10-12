<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use stdClass;

class CustomClassMetadataFactory extends ClassMetadataFactory
{
    public function isTransient(string $className): bool
    {
        return $className !== stdClass::class && parent::isTransient($className);
    }
}
