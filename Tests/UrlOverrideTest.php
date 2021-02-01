<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\DbalTestKernel;
use Generator;

class UrlOverrideTest extends TestCase
{
    /**
     * @dataProvider connectionDataProvider
     */
    public function testConnectionConfiguration(array $config, array $expectedParams): void
    {
        $kernel = new UrlOverrideTestKernel($config);
        $kernel->boot();

        $doctrine = $kernel->getContainer()->get('doctrine');
        $params   = $doctrine->getConnection()->getParams();

        foreach ($expectedParams as $paramName => $value) {
            self::assertSame($value, $params[$paramName]);
        }
    }

    public function connectionDataProvider(): Generator
    {
        yield [
            [
                'override_url' => true,
                'url' => 'mysql://database/main?serverVersion=mariadb-10.5.8',
                'password' => 'wordPass',
                'host' => '127.0.0.1',
            ],
            [
                'user' => 'root',
                'password' => 'wordPass',
                'host' => '127.0.0.1',
                'port' => null,
                'dbname' => 'main',
            ],
        ];

        yield [
            [
                'override_url' => true,
                'url' => 'mysql://someone@database/main?serverVersion=mariadb-10.5.8',
                'user' => 'someone',
            ],
            [
                'user' => 'someone',
                'password' => null,
                'host' => 'database',
                'port' => null,
                'dbname' => 'main',
            ],
        ];

        yield [
            [
                'override_url' => true,
                'url' => 'mysql://database/main?serverVersion=mariadb-10.5.8',
            ],
            [
                'user' => 'root',
                'password' => null,
                'host' => 'database',
                'port' => null,
                'dbname' => 'main',
            ],
        ];
    }
}

class UrlOverrideTestKernel extends DbalTestKernel
{
    public function __construct(array $dbalConfig)
    {
        parent::__construct($dbalConfig);
    }
}
