<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use ProxyManager\Proxy\GhostObjectInterface;

interface GhostObjectEntityManagerInterface extends GhostObjectInterface, EntityManagerInterface
{
}
