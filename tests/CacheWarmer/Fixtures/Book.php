<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\CacheWarmer\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Book
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::INTEGER)]
        public int $id,
    ) {
    }
}
