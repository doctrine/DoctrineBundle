<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public int|null $id = null;

    public function __construct(
        #[ORM\Column(type: Types::STRING)]
        public string $title,
    ) {
    }
}
