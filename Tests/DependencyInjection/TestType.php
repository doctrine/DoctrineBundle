<?php


namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class TestType extends \Doctrine\DBAL\Types\Type
{
    public function getName()
    {
        return 'test';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return '';
    }
}
