<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsDbalType
{
    public function __construct(public string|null $name = null)
    {
    }
}
