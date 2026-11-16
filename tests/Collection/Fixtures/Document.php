<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;
}
