<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\Type;

use function array_merge;
use function defined;
use function is_subclass_of;
use function trigger_deprecation;

use const PHP_EOL;

/** @psalm-import-type Params from DriverManager */
class ConnectionFactory
{
    /** @var mixed[][] */
    private $typesConfig = [];

    /** @var bool */
    private $initialized = false;

    /** @param mixed[][] $typesConfig */
    public function __construct(array $typesConfig)
    {
        $this->typesConfig = $typesConfig;
    }

    /**
     * Create a connection by name.
     *
     * @param mixed[]               $params
     * @param array<string, string> $mappingTypes
     * @psalm-param Params $params
     *
     * @return Connection
     */
    public function createConnection(array $params, ?Configuration $config = null, ?EventManager $eventManager = null, array $mappingTypes = [])
    {
        if (! $this->initialized) {
            $this->initializeTypes();
        }

        $overriddenOptions = [];
        if (isset($params['connection_override_options'])) {
            trigger_deprecation('doctrine/doctrine-bundle', '2.4', 'The "connection_override_options" connection parameter is deprecated');
            $overriddenOptions = $params['connection_override_options'];
            unset($params['connection_override_options']);
        }

        if (! isset($params['pdo']) && (! isset($params['charset']) || $overriddenOptions || isset($params['dbname_suffix']))) {
            $wrapperClass = null;

            if (isset($params['wrapperClass'])) {
                if (! is_subclass_of($params['wrapperClass'], Connection::class)) {
                    throw DBALException::invalidWrapperClass($params['wrapperClass']);
                }

                $wrapperClass           = $params['wrapperClass'];
                $params['wrapperClass'] = null;
            }

            $connection = DriverManager::getConnection($params, $config, $eventManager);
            $params     = $this->addDatabaseSuffix(array_merge($connection->getParams(), $overriddenOptions));
            $driver     = $connection->getDriver();
            $platform   = $driver->getDatabasePlatform();

            if (! isset($params['charset'])) {
                /** @psalm-suppress UndefinedClass AbstractMySQLPlatform exists since DBAL 3.x only */
                if ($platform instanceof AbstractMySQLPlatform || $platform instanceof MySqlPlatform) {
                    $params['charset'] = 'utf8mb4';

                    /* PARAM_ASCII_STR_ARRAY is defined since doctrine/dbal 3.3
                       doctrine/dbal 3.3.2 adds support for the option "collation"
                       Checking for that constant will no longer be necessary
                       after dropping support for doctrine/dbal 2, since this
                       package requires doctrine/dbal 3.3.2 or higher. */
                    if (isset($params['defaultTableOptions']['collate']) && defined('Doctrine\DBAL\Connection::PARAM_ASCII_STR_ARRAY')) {
                        $params['defaultTableOptions']['collation'] = $params['defaultTableOptions']['collate'];
                        unset($params['defaultTableOptions']['collate']);
                    }

                    $collationOption = defined('Doctrine\DBAL\Connection::PARAM_ASCII_STR_ARRAY') ? 'collation' : 'collate';
                    if (! isset($params['defaultTableOptions'][$collationOption])) {
                        $params['defaultTableOptions'][$collationOption] = 'utf8mb4_unicode_ci';
                    }
                } else {
                    $params['charset'] = 'utf8';
                }
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
     */
    private function getDatabasePlatform(Connection $connection): AbstractPlatform
    {
        try {
            return $connection->getDatabasePlatform();
        } catch (DriverException $driverException) {
            throw new DBALException(
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

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function addDatabaseSuffix(array $params): array
    {
        if (isset($params['dbname']) && isset($params['dbname_suffix'])) {
            $params['dbname'] .= $params['dbname_suffix'];
        }

        foreach ($params['replica'] ?? [] as $key => $replicaParams) {
            if (! isset($replicaParams['dbname'], $replicaParams['dbname_suffix'])) {
                continue;
            }

            $params['replica'][$key]['dbname'] .= $replicaParams['dbname_suffix'];
        }

        if (isset($params['primary']['dbname'], $params['primary']['dbname_suffix'])) {
            $params['primary']['dbname'] .= $params['primary']['dbname_suffix'];
        }

        return $params;
    }
}
