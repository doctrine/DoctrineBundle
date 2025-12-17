<?php

declare(strict_types=1);

namespace Fixtures\Bundles\MappedSuperclassBundle\Entity;

/** @ORM\MappedSuperclass() */
class TestAnnotationEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    public int|null $id = null;
}
