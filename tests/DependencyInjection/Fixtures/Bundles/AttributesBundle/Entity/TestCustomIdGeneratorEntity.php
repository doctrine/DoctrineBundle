<?php

namespace Fixtures\Bundles\AttributesBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TestCustomIdGeneratorEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('my_id_generator')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;
}
