<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;

class ConnectionFactoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Doctrine\\ORM\\Version')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testContainer()
    {
        $typesConfig  = [];
        $factory      = new ConnectionFactory($typesConfig);
        $params       = ['driverClass' => '\\Doctrine\\Bundle\\DoctrineBundle\\Tests\\FakeDriver'];
        $config       = null;
        $eventManager = null;
        $mappingTypes = [0];
        $exception    = new DriverException('', $this->getMockBuilder(Driver\AbstractDriverException::class)->disableOriginalConstructor()->getMock());

        // put the mock into the fake driver
        FakeDriver::$exception = $exception;

        try {
            $factory->createConnection($params, $config, $eventManager, $mappingTypes);
        } catch (\Exception $e) {
            $this->assertTrue(strpos($e->getMessage(), 'can circumvent this by setting') > 0);
            throw $e;
        }
    }
}

/**
 * FakeDriver class to simulate a problem discussed in DoctrineBundle issue #673
 * In order to not use a real database driver we have to create our own fake/mock implementation.
 *
 * @link https://github.com/doctrine/DoctrineBundle/issues/673
 */
class FakeDriver implements Driver
{
    /**
     * Exception Mock
     *
     * @var \Doctrine\DBAL\Exception\DriverException
     */
    public static $exception;

    /**
     * This method gets called to determine the database version which in our case leeds to the problem.
     * So we have to fake the exception a driver would normally throw.
     *
     *
     * @link https://github.com/doctrine/DoctrineBundle/issues/673
     */
    public function getDatabasePlatform()
    {
        throw self::$exception;
    }

    // ----- below this line follow only dummy methods to satisfy the interface requirements ----

    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        throw new \Exception('not implemented');
    }

    public function getSchemaManager(Connection $conn)
    {
        throw new \Exception('not implemented');
    }

    public function getName()
    {
        return 'FakeDriver';
    }

    public function getDatabase(Connection $conn)
    {
        return 'fake_db';
    }
}
