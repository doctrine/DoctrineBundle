<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\PropertyInfo\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class TestEmbeddable
{
    #[ORM\Column(type: Types::STRING)]
    public string $value = '';
}
