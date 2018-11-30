<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;

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

    /**
     * @runInSeparateProcess
     */
    public function testGetConfigTreeBuilderDoNotUseDoctrineCommon()
    {
        $configuration = new Configuration(true);
        $configuration->getConfigTreeBuilder();
        $this->assertFalse(class_exists('Doctrine\Common\Proxy\AbstractProxyFactory', false));
    }
}
