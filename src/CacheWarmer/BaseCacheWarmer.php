<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer as LegacyAbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;

use function class_exists;

// The base cache warmer moved from FrameworkBundle to the Cache component in Symfony 8.2
if (class_exists(AbstractPhpFileCacheWarmer::class)) {
    /** @internal */
    abstract class BaseCacheWarmer extends AbstractPhpFileCacheWarmer
    {
    }
} else {
    /** @internal */
    abstract class BaseCacheWarmer extends LegacyAbstractPhpFileCacheWarmer
    {
    }
}
