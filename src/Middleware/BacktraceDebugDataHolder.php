<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;

use function array_slice;
use function debug_backtrace;
use function in_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

class BacktraceDebugDataHolder extends DebugDataHolder
{
    /** @var array<string, array<int|string, mixed>[]> */
    private array $backtraces = [];

    /** @param string[] $connWithBacktraces */
    public function __construct(
        private readonly array $connWithBacktraces,
        private readonly NPlusOneGuard|null $nPlusOneGuard = null,
    ) {
    }

    public function reset(): void
    {
        parent::reset();

        // The bridge's collector resets the holder directly, without going
        // through kernel.reset, and the guard has to follow or it keeps
        // thinking it already reported what is now forgotten.
        $this->nPlusOneGuard?->reset();

        $this->backtraces = [];
    }

    public function addQuery(string $connectionName, Query $query): void
    {
        parent::addQuery($connectionName, $query);

        if (in_array($connectionName, $this->connWithBacktraces, true)) {
            // array_slice to skip middleware calls in the trace
            $this->backtraces[$connectionName][] = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2);
        }

        // Last: the guard throws in strict mode, and the backtraces are read
        // by the index of the record they belong to, so leaving one of the two
        // behind would shift every trace that follows.
        $this->nPlusOneGuard?->track($connectionName, $query->getSql(), $query->getParams());
    }

    /** @return array<string, array<string, mixed>[]> */
    public function getData(): array
    {
        $dataWithBacktraces = [];

        $data = parent::getData();
        foreach ($data as $connectionName => $dataForConn) {
            $dataWithBacktraces[$connectionName] = $this->getDataForConnection($connectionName, $dataForConn);
        }

        return $dataWithBacktraces;
    }

    /**
     * @param mixed[][] $dataForConn
     *
     * @return mixed[][]
     */
    private function getDataForConnection(string $connectionName, array $dataForConn): array
    {
        $data = [];

        foreach ($dataForConn as $idx => $record) {
            $data[] = $this->addBacktracesIfAvailable($connectionName, $record, $idx);
        }

        return $data;
    }

    /**
     * @param mixed[] $record
     *
     * @return mixed[]
     */
    private function addBacktracesIfAvailable(string $connectionName, array $record, int $idx): array
    {
        if (! isset($this->backtraces[$connectionName])) {
            return $record;
        }

        $record['backtrace'] = $this->backtraces[$connectionName][$idx];

        return $record;
    }
}
