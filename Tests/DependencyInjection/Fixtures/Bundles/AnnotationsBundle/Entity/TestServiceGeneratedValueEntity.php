<?php

namespace Fixtures\Bundles\AnnotationsBundle\Entity;

use Doctrine\Bundle\DoctrineBundle\Mapping\ServiceGeneratedValue;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class TestServiceGeneratedValueEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ServiceGeneratedValue(id="my_id_generator", method="theMethod", arguments={123})
     *
     * @var int
     */
    public $id;
}
