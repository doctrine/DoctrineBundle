<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures\Tenant;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'projects')]
class Project
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::INTEGER)]
        public int $id,
        #[ORM\Column(type: Types::STRING)]
        public string $name,
    ) {
    }
}
