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

namespace Doctrine\Bundle\DoctrineBundle\DataCollector;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\ORM\Version;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector as BaseCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * DoctrineDataCollector.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class DoctrineDataCollector extends BaseCollector
{
    private $registry;
    private $invalidEntityCount;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;

        parent::__construct($registry);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        $errors = array();
        $entities = array();
        $caches = array(
            'enabled' => false,
            'log_enabled' => false,
            'counts' => array(
                'puts' => 0,
                'hits' => 0,
                'misses' => 0,
            ),
            'regions' => array(
                'puts' => array(),
                'hits' => array(),
                'misses' => array(),
            ),
        );

        foreach ($this->registry->getManagers() as $name => $em) {
            $entities[$name] = array();
            /** @var $factory \Doctrine\ORM\Mapping\ClassMetadataFactory */
            $factory = $em->getMetadataFactory();
            $validator = new SchemaValidator($em);

            /** @var $class \Doctrine\ORM\Mapping\ClassMetadataInfo */
            foreach ($factory->getLoadedMetadata() as $class) {
                if (!isset($entities[$name][$class->getName()])) {
                    $classErrors = $validator->validateClass($class);
                    $entities[$name][$class->getName()] = $class->getName();

                    if (!empty($classErrors)) {
                        $errors[$name][$class->getName()] = $classErrors;
                    }
                }
            }

            if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
                continue;
            }

            /** @var $emConfig \Doctrine\ORM\Configuration */
            $emConfig = $em->getConfiguration();
            $slcEnabled = $emConfig->isSecondLevelCacheEnabled();

            if (!$slcEnabled) {
                continue;
            }

            $caches['enabled'] = true;

            /** @var $cacheConfiguration \Doctrine\ORM\Cache\CacheConfiguration */
            /** @var $cacheLoggerChain \Doctrine\ORM\Cache\Logging\CacheLoggerChain */
            $cacheConfiguration = $emConfig->getSecondLevelCacheConfiguration();
            $cacheLoggerChain = $cacheConfiguration->getCacheLogger();

            if (!$cacheLoggerChain || !$cacheLoggerChain->getLogger('statistics')) {
                continue;
            }

            /** @var $cacheLoggerStats \Doctrine\ORM\Cache\Logging\StatisticsCacheLogger */
            $cacheLoggerStats = $cacheLoggerChain->getLogger('statistics');
            $caches['log_enabled'] = true;

            $caches['counts']['puts'] += $cacheLoggerStats->getPutCount();
            $caches['counts']['hits'] += $cacheLoggerStats->getHitCount();
            $caches['counts']['misses'] += $cacheLoggerStats->getMissCount();

            foreach ($cacheLoggerStats->getRegionsPut() as $key => $value) {
                if (!isset($caches['regions']['puts'][$key])) {
                    $caches['regions']['puts'][$key] = 0;
                }

                $caches['regions']['puts'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsHit() as $key => $value) {
                if (!isset($caches['regions']['hits'][$key])) {
                    $caches['regions']['hits'][$key] = 0;
                }

                $caches['regions']['hits'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsMiss() as $key => $value) {
                if (!isset($caches['regions']['misses'][$key])) {
                    $caches['regions']['misses'][$key] = 0;
                }

                $caches['regions']['misses'][$key] += $value;
            }
        }

        // HttpKernel < 3.2 compatibility layer
        if (method_exists($this, 'cloneVar')) {
            // Might be good idea to replicate this block in doctrine bridge so we can drop this from here after some time.
            // This code is compatible with such change, because cloneVar is supposed to check if input is already cloned.
            foreach ($this->data['queries'] as &$queries) {
                foreach ($queries as &$query) {
                    $query['params'] = $this->cloneVar($query['params']);
                }
            }
        }

        $this->data['entities'] = $entities;
        $this->data['errors'] = $errors;
        $this->data['caches'] = $caches;
    }

    public function getEntities()
    {
        return $this->data['entities'];
    }

    public function getMappingErrors()
    {
        return $this->data['errors'];
    }

    public function getCacheHitsCount()
    {
        return $this->data['caches']['counts']['hits'];
    }

    public function getCachePutsCount()
    {
        return $this->data['caches']['counts']['puts'];
    }

    public function getCacheMissesCount()
    {
        return $this->data['caches']['counts']['misses'];
    }

    public function getCacheEnabled()
    {
        return $this->data['caches']['enabled'];
    }

    public function getCacheRegions()
    {
        return $this->data['caches']['regions'];
    }

    public function getCacheCounts()
    {
        return $this->data['caches']['counts'];
    }

    public function getInvalidEntityCount()
    {
        if (null === $this->invalidEntityCount) {
            $this->invalidEntityCount = array_sum(array_map('count', $this->data['errors']));
        }

        return $this->invalidEntityCount;
    }

    public function getGroupedQueries()
    {
        static $groupedQueries = null;

        if ($groupedQueries !== null) {
            return $groupedQueries;
        }

        $groupedQueries = array();
        $totalExecutionMS = 0;
        foreach ($this->data['queries'] as $connection => $queries) {
            $connectionGroupedQueries = array();
            foreach ($queries as $i => $query) {
                $key = $query['sql'];
                if (!isset($connectionGroupedQueries[$key])) {
                    $connectionGroupedQueries[$key] = $query;
                    $connectionGroupedQueries[$key]['executionMS'] = 0;
                    $connectionGroupedQueries[$key]['count'] = 0;
                    $connectionGroupedQueries[$key]['index'] = $i; // "Explain query" relies on query index in 'queries'.
                }
                $connectionGroupedQueries[$key]['executionMS'] += $query['executionMS'];
                $connectionGroupedQueries[$key]['count']++;
                $totalExecutionMS += $query['executionMS'];
            }
            usort($connectionGroupedQueries, function ($a, $b) {
                if ($a['executionMS'] === $b['executionMS']) {
                    return 0;
                }
                return ($a['executionMS'] < $b['executionMS']) ? 1 : -1;
            });
            $groupedQueries[$connection] = $connectionGroupedQueries;
        }

        foreach ($groupedQueries as $connection => $queries) {
            foreach ($queries as $i => $query) {
                $groupedQueries[$connection][$i]['executionPercent'] =
                    $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
            }
        }

        return $groupedQueries;
    }

    private function executionTimePercentage($executionTimeMS, $totalExecutionTimeMS)
    {
        if ($totalExecutionTimeMS === 0.0 || $totalExecutionTimeMS === 0) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }

    public function getGroupedQueryCount()
    {
        $count = 0;
        foreach ($this->getGroupedQueries() as $connectionGroupedQueries) {
            $count += count($connectionGroupedQueries);
        }

        return $count;
    }
}
