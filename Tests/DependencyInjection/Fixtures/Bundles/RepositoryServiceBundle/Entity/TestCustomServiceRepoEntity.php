<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository;

/** @ORM\Entity(repositoryClass="Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository") */
#[ORM\Entity(repositoryClass: TestCustomServiceRepoRepository::class)]
class TestCustomServiceRepoEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'AUTO'), ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
}
