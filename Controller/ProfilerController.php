<?php

namespace Doctrine\Bundle\DoctrineBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\Persistence\ConnectionRegistry;
use Exception;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;
use Twig\Environment;

use function assert;

/** @internal */
class ProfilerController
{
    private Environment $twig;
    private ConnectionRegistry $registry;
    private Profiler $profiler;

    public function __construct(Environment $twig, ConnectionRegistry $registry, Profiler $profiler)
    {
        $this->twig     = $twig;
        $this->registry = $registry;
        $this->profiler = $profiler;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token          The profiler token
     * @param string $connectionName
     * @param int    $query
     *
     * @return Response A Response instance
     */
    public function explainAction($token, $connectionName, $query)
    {
        $this->profiler->disable();

        $profile   = $this->profiler->loadProfile($token);
        $collector = $profile->getCollector('db');

        assert($collector instanceof DoctrineDataCollector);

        $queries = $collector->getQueries();

        if (! isset($queries[$connectionName][$query])) {
            return new Response('This query does not exist.');
        }

        $query = $queries[$connectionName][$query];
        if (! $query['explainable']) {
            return new Response('This query cannot be explained.');
        }

        $connection = $this->registry->getConnection($connectionName);
        assert($connection instanceof Connection);
        try {
            $platform = $connection->getDatabasePlatform();
            if ($platform instanceof SQLitePlatform) {
                $results = $this->explainSQLitePlatform($connection, $query);
            } elseif ($platform instanceof SQLServerPlatform) {
                throw new Exception('Explain for SQLServerPlatform is currently not supported. Contributions are welcome.');
            } elseif ($platform instanceof OraclePlatform) {
                $results = $this->explainOraclePlatform($connection, $query);
            } else {
                $results = $this->explainOtherPlatform($connection, $query);
            }
        } catch (Throwable $e) {
            return new Response('This query cannot be explained.');
        }

        return new Response($this->twig->render('@Doctrine/Collector/explain.html.twig', [
            'data' => $results,
            'query' => $query,
        ]));
    }

    /**
     * @param mixed[] $query
     *
     * @return mixed[]
     */
    private function explainSQLitePlatform(Connection $connection, array $query): array
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->executeQuery('EXPLAIN QUERY PLAN ' . $query['sql'], $params, $query['types'])
            ->fetchAllAssociative();
    }

    /**
     * @param mixed[] $query
     *
     * @return mixed[]
     */
    private function explainOtherPlatform(Connection $connection, array $query): array
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->executeQuery('EXPLAIN ' . $query['sql'], $params, $query['types'])
            ->fetchAllAssociative();
    }

    /**
     * @param mixed[] $query
     *
     * @return mixed[]
     */
    private function explainOraclePlatform(Connection $connection, array $query): array
    {
        $connection->executeQuery('EXPLAIN PLAN FOR ' . $query['sql']);

        return $connection->executeQuery('SELECT * FROM TABLE(DBMS_XPLAN.DISPLAY())')
            ->fetchAllAssociative();
    }
}
