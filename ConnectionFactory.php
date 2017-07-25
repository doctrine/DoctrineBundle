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

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Type;

/**
 * Connection
 */
class ConnectionFactory
{
    private $typesConfig = array();
    private $commentedTypes = array();
    private $initialized = false;

    /**
     * Construct.
     *
     * @param array $typesConfig
     */
    public function __construct(array $typesConfig)
    {
        $this->typesConfig = $typesConfig;
    }

    /**
     * Create a connection by name.
     *
     * @param array         $params
     * @param Configuration $config
     * @param EventManager  $eventManager
     * @param array         $mappingTypes
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function createConnection(array $params, Configuration $config = null, EventManager $eventManager = null, array $mappingTypes = array())
    {
        if (!$this->initialized) {
            $this->initializeTypes();
        }

        $connection = DriverManager::getConnection($params, $config, $eventManager);

        if (!empty($mappingTypes)) {
            $platform = $this->getDatabasePlatform($connection);
            foreach ($mappingTypes as $dbType => $doctrineType) {
                $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
            }
        }
        if (!empty($this->commentedTypes)) {
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
     * @param  \Doctrine\DBAL\Connection $connection
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getDatabasePlatform(Connection $connection)
    {
        try {
            return $connection->getDatabasePlatform();
        } catch (DBALException $driverException) {
            if ($driverException instanceof DriverException) {
                throw new DBALException(
                    "An exception occured while establishing a connection to figure out your platform version." . PHP_EOL .
                    "You can circumvent this by setting a 'server_version' configuration value" . PHP_EOL . PHP_EOL .
                    "For further information have a look at:" . PHP_EOL .
                    "https://github.com/doctrine/DoctrineBundle/issues/673",
                    0,
                    $driverException
                );
            }
            throw $driverException;
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
            if ($typeConfig['commented']) {
                $this->commentedTypes[] = $type;
            }
        }
        $this->initialized = true;
    }
}
