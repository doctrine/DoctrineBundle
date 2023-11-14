<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use ProxyManager\Proxy\LazyLoadingInterface;

/**
 * @template LazilyLoadedObjectType of object
 * @extends LazyLoadingInterface<LazilyLoadedObjectType>
 */
interface LazyLoadingEntityManagerInterface extends LazyLoadingInterface, EntityManagerInterface
{
}
