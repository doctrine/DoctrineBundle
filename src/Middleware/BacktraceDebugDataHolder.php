<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;

use function array_slice;
use function debug_backtrace;
use function in_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

class BacktraceDebugDataHolder extends DebugDataHolder
{
    /** @var array<string, array<int|string, mixed>[]> */
    private array $backtraces = [];

    private readonly TwigBacktraceMapper $twigMapper;

    /** @param string[] $connWithBacktraces */
    public function __construct(
        private readonly array $connWithBacktraces,
        TwigBacktraceMapper|null $twigMapper = null,
    ) {
        $this->twigMapper = $twigMapper ?? new TwigBacktraceMapper();
    }

    public function reset(): void
    {
        parent::reset();

        $this->backtraces = [];
    }

    public function addQuery(string $connectionName, Query $query): void
    {
        parent::addQuery($connectionName, $query);

        if (! in_array($connectionName, $this->connWithBacktraces, true)) {
            return;
        }

        // array_slice to skip middleware calls in the trace. Objects are
        // needed to map a compiled Twig template back to its source.
        $this->backtraces[$connectionName][] = $this->twigMapper->map(
            array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT), 2),
        );
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
