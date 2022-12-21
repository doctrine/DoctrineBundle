<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
#[ORM\Entity]
class TestDefaultRepoEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'AUTO'), ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
}
