<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;

/** @ORM\Entity(repositoryClass="Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository") */
#[ORM\Entity(repositoryClass: TestCustomClassRepoRepository::class)]
class TestCustomClassRepoEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
}
