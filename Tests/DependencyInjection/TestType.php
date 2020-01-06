<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TestType extends Type
{
    public function getName() : string
    {
        return 'test';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        return '';
    }
}
