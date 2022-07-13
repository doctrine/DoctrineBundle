<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\VarExporter\LazyGhostObjectInterface;

interface LazyGhostObjectEntityManagerInterface extends LazyGhostObjectInterface, EntityManagerInterface
{
}
