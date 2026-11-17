<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Carries the entity attribute, but no entity manager maps it. */
#[ORM\Entity]
class UnmappedUser
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public int|null $id = null;
}
