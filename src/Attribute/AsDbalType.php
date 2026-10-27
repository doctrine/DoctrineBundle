<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Registers the class as a DBAL type, named after the class unless a name is given.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsDbalType
{
    public function __construct(public string|null $name = null)
    {
    }
}
