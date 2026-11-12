<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Profiling\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;

    #[ORM\Column]
    public string $title = '';

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'posts')]
    public Author|null $author = null;
}
