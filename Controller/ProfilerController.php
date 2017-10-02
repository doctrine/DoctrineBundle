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

namespace Doctrine\Bundle\DoctrineBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProfilerController.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ProfilerController implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string  $token          The profiler token
     * @param string  $connectionName
     * @param integer $query
     *
     * @return Response A Response instance
     */
    public function explainAction($token, $connectionName, $query)
    {
        /** @var $profiler \Symfony\Component\HttpKernel\Profiler\Profiler */
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profile = $profiler->loadProfile($token);
        $queries = $profile->getCollector('db')->getQueries();

        if (!isset($queries[$connectionName][$query])) {
            return new Response('This query does not exist.');
        }

        $query = $queries[$connectionName][$query];
        if (!$query['explainable']) {
            return new Response('This query cannot be explained.');
        }

        /** @var $connection \Doctrine\DBAL\Connection */
        $connection = $this->container->get('doctrine')->getConnection($connectionName);
        try {
            if ($connection->getDatabasePlatform() instanceof SQLServerPlatform) {
                $results = $this->explainSQLServerPlatform($connection, $query);
            } else {
                $results = $this->explainOtherPlatform($connection, $query);
            }
        } catch (\Exception $e) {
            return new Response('This query cannot be explained.');
        }

        return new Response($this->container->get('twig')->render('@Doctrine/Collector/explain.html.twig', array(
            'data' => $results,
            'query' => $query,
        )));
    }

    private function explainSQLServerPlatform(Connection $connection, $query)
    {
        if (stripos($query['sql'], 'SELECT') === 0) {
            $sql = 'SET STATISTICS PROFILE ON; ' . $query['sql'] . '; SET STATISTICS PROFILE OFF;';
        } else {
            $sql = 'SET SHOWPLAN_TEXT ON; GO; SET NOEXEC ON; ' . $query['sql'] .'; SET NOEXEC OFF; GO; SET SHOWPLAN_TEXT OFF;';
        }
        $stmt = $connection->executeQuery($sql, $query['params'], $query['types']);
        $stmt->nextRowset();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function explainOtherPlatform(Connection $connection, $query)
    {
        return $connection->executeQuery('EXPLAIN '.$query['sql'], $query['params'], $query['types'])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }
}
