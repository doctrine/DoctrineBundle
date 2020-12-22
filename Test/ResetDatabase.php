<?php

namespace Doctrine\Bundle\DoctrineBundle\Test;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function is_a;
use function sprintf;

trait ResetDatabase
{
    /** @var bool */
    private static $hasDatabaseBeenReset = false;

    /**
     * @internal
     *
     * @beforeClass
     */
    public static function resetDatabase(): void
    {
        if (DatabaseResetter::hasBeenReset()) {
            // only reset before first test
            return;
        }

        if (! is_a(static::class, KernelTestCase::class, true)) {
            throw new RuntimeException(sprintf('The "%s" trait can only be used on TestCases that extend "%s".', __TRAIT__, KernelTestCase::class));
        }

        $kernel = static::createKernel();
        $kernel->boot();

        DatabaseResetter::resetDatabase($kernel);

        $kernel->shutdown();
    }

    /**
     * @internal
     *
     * @before
     */
    public static function resetSchema(): void
    {
        $kernel = static::createKernel();
        $kernel->boot();

        DatabaseResetter::resetSchema($kernel);

        $kernel->shutdown();
    }
}
