<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Doctrine\Bundle\DoctrineBundle\Exception\NPlusOneQueriesDetected;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function count;
use function get_resource_id;
use function get_resource_type;
use function hash;
use function is_array;
use function is_object;
use function is_resource;
use function serialize;
use function spl_object_id;
use function sprintf;

/**
 * Reports statements that get replayed with many different parameter sets.
 *
 * That is the signature of N+1 queries caused by ORM lazy loading: the same
 * SELECT, once per parent row. The guard runs while the queries are being
 * issued, so a thrown report points straight at the code that triggered the
 * lazy load, which a profile read afterwards cannot.
 *
 * Each statement is reported once; its parameters are dropped afterwards so a
 * long-running worker does not accumulate them.
 */
class NPlusOneGuard implements ResetInterface
{
    /** @var array<string, array<string, array<string, true>>> */
    private array $paramSets = [];

    /** @var array<string, array<string, true>> */
    private array $reported = [];

    /**
     * @param array<string, int>  $thresholdByConnection Number of distinct parameter sets a statement may be replayed with, per connection
     * @param array<string, bool> $strictByConnection    Connections where reaching the threshold throws instead of being logged
     */
    public function __construct(
        private readonly array $thresholdByConnection,
        private readonly array $strictByConnection = [],
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /** @param array<array-key, mixed> $params */
    public function track(string $connectionName, string $sql, array $params): void
    {
        $threshold = $this->thresholdByConnection[$connectionName] ?? 0;
        if ($threshold < 2 || isset($this->reported[$connectionName][$sql])) {
            return;
        }

        $this->paramSets[$connectionName][$sql][self::hashParams($params)] = true;

        if (count($this->paramSets[$connectionName][$sql]) < $threshold) {
            return;
        }

        $this->reported[$connectionName][$sql] = true;
        unset($this->paramSets[$connectionName][$sql]);

        $message = sprintf(
            'Statement replayed with %d different parameter sets on connection "%s", which is the signature of N+1 queries. Fetch the association together with its parent instead: %s',
            $threshold,
            $connectionName,
            $sql,
        );

        if ($this->strictByConnection[$connectionName] ?? false) {
            throw new NPlusOneQueriesDetected($message);
        }

        $this->logger?->warning($message, ['connection' => $connectionName, 'sql' => $sql]);
    }

    public function reset(): void
    {
        $this->paramSets = [];
        $this->reported  = [];
    }

    /**
     * Returns a fixed-size identity for a set of parameters.
     *
     * Resources all serialize to the same value, which would hide the very
     * repetitions being looked for, and some objects cannot be serialized at
     * all: both are replaced by a marker.
     *
     * @param array<array-key, mixed> $params
     */
    private static function hashParams(array $params): string
    {
        return hash('xxh128', serialize(self::normalizeParams($params)));
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeParams(array $params): array
    {
        foreach ($params as $key => $param) {
            if (is_array($param)) {
                $params[$key] = self::normalizeParams($param);
            } elseif (is_resource($param)) {
                $params[$key] = sprintf('/* Resource(%s)#%d */', get_resource_type($param), get_resource_id($param));
            } elseif (is_object($param)) {
                try {
                    $params[$key] = serialize($param);
                } catch (Throwable) {
                    $params[$key] = sprintf('/* %s#%d */', $param::class, spl_object_id($param));
                }
            }
        }

        return $params;
    }
}
