<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connection\StaticServerVersionProvider;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\InvalidWrapperClass;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function array_keys;
use function array_merge;
use function assert;
use function is_subclass_of;
use function method_exists;

use const PHP_EOL;

/**
 * @internal This class is not meant to be used outside this bundle
 *
 * @phpstan-type Params = array<string, mixed>
 */
final class ConnectionFactory
{
    /** @internal */
    public const array DEFAULT_SCHEME_MAP = [
        'db2'        => 'ibm_db2',
        'mssql'      => 'pdo_sqlsrv',
        'mysql'      => 'pdo_mysql',
        'mysql2'     => 'pdo_mysql', // Amazon RDS, for some weird reason
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'sqlite3'    => 'pdo_sqlite',
    ];

    private readonly DsnParser $dsnParser;

    private bool $initialized = false;

    /**
     * @param ServiceProviderInterface<Type>|null $types The types to register in the global type
     *        registry, keyed by type name. The container builds them, so their dependencies are
     *        injected.
     */
    public function __construct(
        private readonly ServiceProviderInterface|null $types = null,
        DsnParser|null $dsnParser = null,
    ) {
        $this->dsnParser = $dsnParser ?? new DsnParser(self::DEFAULT_SCHEME_MAP);
    }

    /**
     * Create a connection by name.
     *
     * @param array<string, string> $mappingTypes
     * @phpstan-param Params $params
     */
    public function createConnection(
        array $params,
        Configuration|null $config = null,
        array $mappingTypes = [],
    ): Connection {
        if (! $this->initialized) {
            $this->initializeTypes();
        }

        $params = $this->parseDatabaseUrl($params);

        // URL support for PrimaryReplicaConnection
        if (isset($params['primary'])) {
            $params['primary'] = $this->parseDatabaseUrl($params['primary']);
        }

        if (isset($params['replica'])) {
            foreach ($params['replica'] as $key => $replicaParams) {
                $params['replica'][$key] = $this->parseDatabaseUrl($replicaParams);
            }
        }

        if (! isset($params['pdo']) && (! isset($params['charset']) || isset($params['dbname_suffix']))) {
            $wrapperClass = null;

            if (isset($params['wrapperClass'])) {
                if (! is_subclass_of($params['wrapperClass'], Connection::class)) {
                    throw InvalidWrapperClass::new($params['wrapperClass']);
                }

                $wrapperClass           = $params['wrapperClass'];
                $params['wrapperClass'] = null;
            }

            $connection = DriverManager::getConnection($params, $config);
            $params     = $this->addDatabaseSuffix($connection->getParams());
            $driver     = $connection->getDriver();

            if (! isset($params['charset'])) {
                $platform = $driver->getDatabasePlatform(new StaticServerVersionProvider(
                    $params['serverVersion'] ?? $params['primary']['serverVersion'] ?? '',
                ));

                if ($platform instanceof AbstractMySQLPlatform) {
                    $params['charset'] = 'utf8mb4';
                    if (! isset($params['defaultTableOptions']['collation'])) {
                        $params['defaultTableOptions']['collation'] = 'utf8mb4_unicode_ci';
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

            $connection = new $wrapperClass($params, $driver, $config);
        } else {
            $connection = DriverManager::getConnection($params, $config);
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
     *
     * @link https://github.com/doctrine/DoctrineBundle/issues/673
     *
     * @throws DBALException
     */
    private function getDatabasePlatform(Connection $connection): AbstractPlatform
    {
        try {
            return $connection->getDatabasePlatform();
        } catch (DriverException $driverException) {
            throw new ConnectionException(
                'An exception occurred while establishing a connection to figure out your platform version.' . PHP_EOL .
                "You can circumvent this by setting a 'server_version' configuration value" . PHP_EOL . PHP_EOL .
                'For further information have a look at:' . PHP_EOL .
                'https://github.com/doctrine/DoctrineBundle/issues/673',
                0,
                $driverException,
            );
        }
    }

    /**
     * Registers the types built by the container in the global type registry.
     *
     * The types are registered as instances, so they keep the dependencies injected by the
     * container. They are registered globally too, because Doctrine ORM before 3.8 resolves them
     * through the global registry instead of the type provider of the connection.
     */
    private function initializeTypes(): void
    {
        if ($this->types === null) {
            $this->initialized = true;

            return;
        }

        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        if (method_exists(Configuration::class, 'getTypeProvider')) {
            // A default Configuration exposes the global type registry as its type provider.
            // Reading it that way does not trigger the deprecation of Type::getTypeRegistry().
            $registry = (new Configuration())->getTypeProvider();
        } else {
            $registry = Type::getTypeRegistry();
        }

        assert($registry instanceof TypeRegistry);

        foreach (array_keys($this->types->getProvidedServices()) as $typeName) {
            $type = $this->types->get($typeName);

            if ($registry->has($typeName)) {
                $registry->override($typeName, $type);
            } else {
                $registry->register($typeName, $type);
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

    /**
     * Extracts parts from a database URL, if present, and returns an
     * updated list of parameters.
     *
     * @param mixed[] $params The list of parameters.
     * @phpstan-param Params $params
     *
     * @return Params params A modified list of parameters with info from a database
     *                 URL extracted into individual parameter parts.
     * @phpstan-return Params
     *
     * @throws DBALException
     */
    private function parseDatabaseUrl(array $params): array
    {
        if (! isset($params['url'])) {
            return $params;
        }

        try {
            $parsedParams = $this->dsnParser->parse($params['url']);
        } catch (MalformedDsnException $e) {
            throw new MalformedDsnException('Malformed parameter "url".', 0, $e);
        }

        if (isset($parsedParams['driver'])) {
            // The requested driver from the URL scheme takes precedence
            // over the default custom driver from the connection parameters (if any).
            unset($params['driverClass']);
        }

        $params = array_merge($params, $parsedParams);

        // If a schemeless connection URL is given, we require a default driver or default custom driver
        // as connection parameter.
        if (! isset($params['driverClass']) && ! isset($params['driver'])) {
            throw DriverRequired::new($params['url']);
        }

        unset($params['url']);

        return $params;
    }
}
