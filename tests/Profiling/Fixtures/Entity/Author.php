<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Profiling\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;

    #[ORM\Column]
    public string $name = '';

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    public Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }
}
