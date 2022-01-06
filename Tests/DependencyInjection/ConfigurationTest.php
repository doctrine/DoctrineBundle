<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function extension_loaded;

use const PHP_VERSION_ID;

class ConfigurationTest extends TestCase
{
    /**
     * Whether or not this test should preserve the global state when
     * running in a separate PHP process.
     *
     * PHPUnit hack to avoid currently loaded classes to leak to
     * testGetConfigTreeBuilderDoNotUseDoctrineCommon that is run in separate process.
     *
     * @var bool
     */
    protected $preserveGlobalState = false;

    /** @runInSeparateProcess */
    public function testGetConfigTreeBuilderDoNotUseDoctrineCommon(): void
    {
        if (extension_loaded('pcov') && PHP_VERSION_ID >= 80100) {
            $this->markTestSkipped('Segfaults, see https://github.com/krakjoe/pcov/issues/84');
        }

        $configuration = new Configuration(true);
        $configuration->getConfigTreeBuilder();

        $this->assertFalse(class_exists('Doctrine\Common\Proxy\AbstractProxyFactory', false));
    }
}
