<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MoneyEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id = 1;

    #[ORM\Column(type: 'money')]
    private string $amount = '0.00';
}
