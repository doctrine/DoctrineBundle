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
use InvalidArgumentException;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function array_keys;
use function array_merge;
use function is_subclass_of;
use function method_exists;
use function spl_object_id;
use function sprintf;

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
     * @param mixed[][]                           $typesConfig
     * @param ServiceProviderInterface<Type>|null $globalTypes Types that must also be registered
     *                                                        in the global type registry, keyed by type name
     */
    public function __construct(
        private readonly array $typesConfig = [],
        DsnParser|null $dsnParser = null,
        private readonly ServiceProviderInterface|null $globalTypes = null,
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
            $platform   = $driver->getDatabasePlatform(new StaticServerVersionProvider(
                $params['serverVersion'] ?? $params['primary']['serverVersion'] ?? '',
            ));

            if (! isset($params['charset'])) {
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
     * initialize the types
     */
    private function initializeTypes(): void
    {
        // A default Configuration exposes the global type registry as its type provider. Reading
        // it that way does not trigger the deprecation of Type::getTypeRegistry(). It is null on
        // DBAL < 4.6, where the static methods of Type are the only way and are not deprecated.
        /** @phpstan-ignore function.alreadyNarrowedType (kept for DBAL < 4.6 compatibility) */
        $provider = method_exists(Configuration::class, 'getTypeProvider')
            ? (new Configuration())->getTypeProvider()
            : null;

        $registry = $provider instanceof TypeRegistry ? $provider : null;

        foreach ($this->typesConfig as $typeName => $typeConfig) {
            $this->registerGlobalType($registry, $typeName, $typeConfig['class']);
        }

        $this->registerGlobalTypes($registry);

        $this->initialized = true;
    }

    /**
     * Registers a type in the global type registry.
     *
     * @param TypeRegistry|null       $registry The global registry, or null on DBAL < 4.6
     * @param class-string<Type>|Type $type
     */
    private function registerGlobalType(TypeRegistry|null $registry, string $typeName, string|Type $type): void
    {
        if ($registry === null) {
            if (Type::hasType($typeName)) {
                Type::overrideType($typeName, $type);
            } else {
                Type::addType($typeName, $type);
            }

            return;
        }

        if ($registry->has($typeName)) {
            $registry->override($typeName, $type);
        } else {
            $registry->register($typeName, $type);
        }
    }

    /**
     * Registers the types provided by the container in the global type registry.
     *
     * Doctrine ORM before 3.8 resolves types through the global registry instead of the type
     * provider of the connection. The types are registered as instances, which keeps the
     * dependencies injected by the container.
     */
    private function registerGlobalTypes(TypeRegistry|null $registry): void
    {
        $globalTypes = $this->globalTypes;
        if ($globalTypes === null) {
            return;
        }

        // A service can provide several type names, for example with a repeated #[AsDbalType]
        // attribute. The global registry refuses the same instance under two names, so the extra
        // names get a copy of the instance.
        $registered = [];

        foreach (array_keys($globalTypes->getProvidedServices()) as $typeName) {
            $type = $globalTypes->get($typeName);

            // The locator is typed by convention, a misconfigured service is caught here.
            /** @phpstan-ignore instanceof.alwaysTrue */
            if (! $type instanceof Type) {
                throw new InvalidArgumentException(sprintf('The service registered for the DBAL type "%s" must be an instance of "%s".', $typeName, Type::class));
            }

            if (isset($registered[spl_object_id($type)])) {
                $type = clone $type;
            }

            $registered[spl_object_id($type)] = true;

            $this->registerGlobalType($registry, $typeName, $type);
        }
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
