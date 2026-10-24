<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDbalType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

#[AsDbalType]
class DbalTypeNoName extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'dbal_type_no_name';
    }
}
