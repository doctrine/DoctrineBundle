<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use ProxyManager\Proxy\LazyLoadingInterface;

interface LazyLoadingEntityManagerInterface extends LazyLoadingInterface, EntityManagerInterface
{
}
