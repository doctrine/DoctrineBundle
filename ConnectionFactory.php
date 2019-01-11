<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Connection
 */
class ConnectionFactory
{
    /** @var mixed[][] */
    private $typesConfig = [];

    /** @var string[] */
    private $commentedTypes = [];

    /** @var bool */
    private $initialized = false;

    /**
     * Construct.
     *
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

        $connection = DriverManager::getConnection($params, $config, $eventManager);

        if (! empty($mappingTypes)) {
            $platform = $this->getDatabasePlatform($connection);
            foreach ($mappingTypes as $dbType => $doctrineType) {
                $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
            }
        }
        if (! empty($this->commentedTypes)) {
            $platform = $this->getDatabasePlatform($connection);
            foreach ($this->commentedTypes as $type) {
                $platform->markDoctrineTypeCommented(Type::getType($type));
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
     * @return AbstractPlatform
     *
     * @throws DBALException
     */
    private function getDatabasePlatform(Connection $connection)
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
    private function initializeTypes()
    {
        foreach ($this->typesConfig as $type => $typeConfig) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $typeConfig['class']);
            } else {
                Type::addType($type, $typeConfig['class']);
            }
            if (! $typeConfig['commented']) {
                continue;
            }

            $this->commentedTypes[] = $type;
        }
        $this->initialized = true;
    }
}
