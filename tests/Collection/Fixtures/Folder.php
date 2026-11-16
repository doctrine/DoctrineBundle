<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;

    public function __construct(
        #[ORM\ManyToMany(targetEntity: Document::class, cascade: ['persist'])]
        public DocumentList $documents,
    ) {
    }
}
