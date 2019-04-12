<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestCommentedType;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Version;
use Exception;

class ConnectionFactoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (class_exists(Version::class)) {
            return;
        }

        $this->markTestSkipped('Doctrine ORM is not available.');
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testContainer()
    {
        $typesConfig  = [];
        $factory      = new ConnectionFactory($typesConfig);
        $params       = ['driverClass' => FakeDriver::class];
        $config       = null;
        $eventManager = null;
        $mappingTypes = [0];
        $exception    = new DriverException('', $this->createMock(Driver\AbstractDriverException::class));

        // put the mock into the fake driver
        FakeDriver::$exception = $exception;

        try {
            $factory->createConnection($params, $config, $eventManager, $mappingTypes);
        } catch (Exception $e) {
            $this->assertTrue(strpos($e->getMessage(), 'can circumvent this by setting') > 0);
            throw $e;
        } finally {
            FakeDriver::$exception = null;
        }
    }

    /**
     * @dataProvider getValidTypeConfigurations
     */
    public function testRegisterTypes(array $type, int $expectedCalls) : void
    {
        $factory      = new ConnectionFactory(['test' => $type]);
        $params       = ['driverClass' => FakeDriver::class];
        $config       = null;
        $eventManager = null;
        $mappingTypes = [];

        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->exactly($expectedCalls))
            ->method('markDoctrineTypeCommented')
            ->with($this->isInstanceOf($type['class']));

        FakeDriver::$platform = $platform;

        try {
            $factory->createConnection($params, $config, $eventManager, $mappingTypes);
        } finally {
            FakeDriver::$platform = null;
        }
    }

    public static function getValidTypeConfigurations() : array
    {
        return [
            'uncommentedTypeMarkedNotCommented' => [
                'type' => [
                    'class' => TestType::class,
                    'commented' => false,
                ],
                'expectedCalls' => 0,
            ],
            'commentedTypeNotMarked' => [
                'type' => [
                    'class' => TestCommentedType::class,
                    'commented' => null,
                ],
                'expectedCalls' => 0,
            ],
        ];
    }

    /**
     * @group legacy
     * @expectedDeprecation The type "test" was implicitly marked as commented due to the configuration. This is deprecated and will be removed in DoctrineBundle 2.0. Either set the "commented" attribute in the configuration to "false" or mark the type as commented in "Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType::requiresSQLCommentHint()."
     */
    public function testRegisterUncommentedTypeNotMarked() : void
    {
        $this->testRegisterTypes(
            [
                'class' => TestType::class,
                'commented' => null,
            ],
            1
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation The type "test" was marked as commented in its configuration but not in the type itself. This is deprecated and will be removed in DoctrineBundle 2.0. Please update the return value of "Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType::requiresSQLCommentHint()."
     */
    public function testRegisterUncommentedTypeMarkedCommented() : void
    {
        $this->testRegisterTypes(
            [
                'class' => TestType::class,
                'commented' => true,
            ],
            1
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation The type "test" was explicitly marked as commented in its configuration. This is no longer necessary and will be removed in DoctrineBundle 2.0. Please remove the "commented" attribute from the type configuration.
     */
    public function testRegisterCommentedTypeMarkedCommented() : void
    {
        $this->testRegisterTypes(
            [
                'class' => TestCommentedType::class,
                'commented' => true,
            ],
            0
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation The type "test" was marked as uncommented in its configuration but commented in the type itself. This is deprecated and will be removed in DoctrineBundle 2.0. Please update the return value of "Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\TestCommentedType::requiresSQLCommentHint()" or remove the "commented" attribute from the type configuration.
     */
    public function testRegisterCommentedTypeMarkedNotCommented() : void
    {
        $this->testRegisterTypes(
            [
                'class' => TestCommentedType::class,
                'commented' => false,
            ],
            0
        );
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
     * @var DriverException
     */
    public static $exception;

    /** @var AbstractPlatform|null */
    public static $platform;

    /**
     * This method gets called to determine the database version which in our case leeds to the problem.
     * So we have to fake the exception a driver would normally throw.
     *
     * @link https://github.com/doctrine/DoctrineBundle/issues/673
     */
    public function getDatabasePlatform()
    {
        if (self::$exception !== null) {
            throw self::$exception;
        }

        return static::$platform ?? new MySqlPlatform();
    }

    // ----- below this line follow only dummy methods to satisfy the interface requirements ----

    /**
     * @param mixed[]     $params
     * @param string|null $username
     * @param string|null $password
     * @param mixed[]     $driverOptions
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        throw new Exception('not implemented');
    }

    public function getSchemaManager(Connection $conn)
    {
        throw new Exception('not implemented');
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
