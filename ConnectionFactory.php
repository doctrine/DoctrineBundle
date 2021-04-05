<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

use function array_merge;
use function class_exists;
use function is_subclass_of;

use const PHP_EOL;

/**
 * @psalm-import-type Params from DriverManager
 */
class ConnectionFactory
{
    /** @var mixed[][] */
    private $typesConfig = [];

    /** @var bool */
    private $initialized = false;

    /**
     * @param mixed[][] $typesConfig
     */
    public function __construct(array $typesConfig)
    {
        $this->typesConfig = $typesConfig;
    }

    /**
     * Create a connection by name.
     *
     * @param mixed[]               $params
     * @param array<string, string> $mappingTypes
     *
     * @return Connection
     *
     * @psalm-param Params $params
     */
    public function createConnection(array $params, ?Configuration $config = null, ?EventManager $eventManager = null, array $mappingTypes = [])
    {
        if (! $this->initialized) {
            $this->initializeTypes();
        }

        $overriddenOptions = $params['connection_override_options'] ?? [];
        unset($params['connection_override_options']);

        if (! isset($params['pdo']) && (! isset($params['charset']) || $overriddenOptions)) {
            $wrapperClass = null;

            if (isset($params['wrapperClass'])) {
                if (! is_subclass_of($params['wrapperClass'], Connection::class)) {
                    if (class_exists(DBALException::class)) {
                        throw DBALException::invalidWrapperClass($params['wrapperClass']);
                    }

                    throw Exception::invalidWrapperClass($params['wrapperClass']);
                }

                $wrapperClass           = $params['wrapperClass'];
                $params['wrapperClass'] = null;
            }

            $connection = DriverManager::getConnection($params, $config, $eventManager);
            $params     = array_merge($connection->getParams(), $overriddenOptions);
            $driver     = $connection->getDriver();

            if ($driver instanceof AbstractMySQLDriver) {
                $params['charset'] = 'utf8mb4';

                if (! isset($params['defaultTableOptions']['collate'])) {
                    $params['defaultTableOptions']['collate'] = 'utf8mb4_unicode_ci';
                }
            } else {
                $params['charset'] = 'utf8';
            }

            if ($wrapperClass !== null) {
                $params['wrapperClass'] = $wrapperClass;
            } else {
                $wrapperClass = Connection::class;
            }

            $connection = new $wrapperClass($params, $driver, $config, $eventManager);
        } else {
            $connection = DriverManager::getConnection($params, $config, $eventManager);
        }

        if (! empty($mappingTypes)) {
            $platform = $this->getDatabasePlatform($connection);
            foreach ($mappingTypes as $dbType => $doctrineType) {
                $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
            }
        }

        return $connection;
    }

    /**
     * Try to get the database platform.
     *
     * This could fail if types should be registered to an predefined/unused connection
     * and the platform version is unknown.
     * For details have a look at DoctrineBundle issue #673.
     *
     * @throws DBALException
     * @throws Exception
     */
    private function getDatabasePlatform(Connection $connection): AbstractPlatform
    {
        try {
            return $connection->getDatabasePlatform();
        } catch (DriverException $driverException) {
            $exceptionClass = class_exists(DBALException::class) ? DBALException::class : Exception::class;

            throw new $exceptionClass(
                'An exception occurred while establishing a connection to figure out your platform version.' . PHP_EOL .
                "You can circumvent this by setting a 'server_version' configuration value" . PHP_EOL . PHP_EOL .
                'For further information have a look at:' . PHP_EOL .
                'https://github.com/doctrine/DoctrineBundle/issues/673',
                0,
                $driverException
            );
        }
    }

    /**
     * initialize the types
     */
    private function initializeTypes(): void
    {
        foreach ($this->typesConfig as $typeName => $typeConfig) {
            if (Type::hasType($typeName)) {
                Type::overrideType($typeName, $typeConfig['class']);
            } else {
                Type::addType($typeName, $typeConfig['class']);
            }
        }

        $this->initialized = true;
    }
}
