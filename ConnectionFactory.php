<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use function is_subclass_of;

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
     * @param mixed[]         $params
     * @param string[]|Type[] $mappingTypes
     *
     * @return Connection
     */
    public function createConnection(array $params, Configuration $config = null, EventManager $eventManager = null, array $mappingTypes = [])
    {
        if (! $this->initialized) {
            $this->initializeTypes();
        }

        if (isset($params['override_url'], $params['url']) && $params['override_url']) {
            $params['url'] = $this->overrideUrl($params);
        }

        if (! isset($params['pdo']) && ! isset($params['charset'])) {
            $wrapperClass = null;
            if (isset($params['wrapperClass'])) {
                if (! is_subclass_of($params['wrapperClass'], Connection::class)) {
                    throw DBALException::invalidWrapperClass($params['wrapperClass']);
                }

                $wrapperClass           = $params['wrapperClass'];
                $params['wrapperClass'] = null;
            }

            $connection = DriverManager::getConnection($params, $config, $eventManager);
            $params     = $connection->getParams();
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
     */
    private function getDatabasePlatform(Connection $connection) : AbstractPlatform
    {
        try {
            return $connection->getDatabasePlatform();
        } catch (DriverException $driverException) {
            throw new DBALException(
                'An exception occured while establishing a connection to figure out your platform version.' . PHP_EOL .
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
    private function initializeTypes() : void
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
     * @param mixed[] $params
     */
    private function overrideUrl(array $params) : string
    {
        if (empty($params['dbname'])) {
            return $params['url'];
        }

        $parsedUrl         = parse_url($params['url']);
        $parsedUrl['path'] = sprintf('/%s', $params['dbname']);

        $newUrl = '';

        foreach (['scheme', 'user', 'pass', 'host', 'port', 'path', 'query'] as $key) {
            if (empty($parsedUrl[$key])) {
                continue;
            }

            switch ($key) {
                case 'scheme':
                    $newUrl = sprintf('%s://', $parsedUrl[$key]);
                    break;
                case 'user':
                case 'path':
                    $newUrl = sprintf('%s%s', $newUrl, $parsedUrl[$key]);
                    break;
                case 'port':
                case 'pass':
                    $newUrl = sprintf('%s:%s', $newUrl, $parsedUrl[$key]);
                    break;
                case 'host':
                    $hostSeparator = ! empty($parsedUrl['user']) ? '@' : '';

                    $newUrl = sprintf(
                        '%s%s%s',
                        $newUrl,
                        $hostSeparator,
                        $parsedUrl[$key]
                    );
                    break;
                case 'query':
                    $newUrl = sprintf('%s?%s', $newUrl, $parsedUrl[$key]);
                    break;
            }
        }

        return $newUrl;
    }
}
