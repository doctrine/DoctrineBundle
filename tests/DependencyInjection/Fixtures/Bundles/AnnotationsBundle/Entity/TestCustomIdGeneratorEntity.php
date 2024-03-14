<?php

namespace Fixtures\Bundles\AnnotationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class TestCustomIdGeneratorEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator("my_id_generator")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;
}
