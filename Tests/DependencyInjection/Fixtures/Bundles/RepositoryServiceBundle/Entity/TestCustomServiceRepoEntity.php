<?php


namespace Fixtures\Bundles\RepositoryServiceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository")
 */
class TestCustomServiceRepoEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;
}
