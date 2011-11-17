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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;

/**
 * Connection
 */
class ConnectionFactory
{
    private $typesConfig = array();
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
     *
     * @return Doctrine\DBAL\Connection
     */
    public function createConnection(array $params, Configuration $config = null, EventManager $eventManager = null, array $mappingTypes = array())
    {
        if (!$this->initialized) {
            $this->initializeTypes();
            $this->initialized = true;
        }

        $connection = DriverManager::getConnection($params, $config, $eventManager);

        if (!empty($mappingTypes)) {
            $platform = $connection->getDatabasePlatform();
            foreach ($mappingTypes as $dbType => $doctrineType) {
                $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
            }
        }

        return $connection;
    }

    private function initializeTypes()
    {
        foreach ($this->typesConfig as $type => $className) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $className);
            } else {
                Type::addType($type, $className);
            }
        }
    }
}
