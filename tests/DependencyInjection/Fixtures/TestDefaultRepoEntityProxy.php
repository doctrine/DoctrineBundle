<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Persistence\Proxy;
use Fixtures\Bundles\RepositoryServiceBundle\Entity\TestDefaultRepoEntity;

/** @implements Proxy<TestDefaultRepoEntity> */
class TestDefaultRepoEntityProxy extends TestDefaultRepoEntity implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): bool
    {
        return true;
    }
}
