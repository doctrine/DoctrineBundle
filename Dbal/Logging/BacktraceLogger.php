<?php

namespace Doctrine\Bundle\DoctrineBundle\Dbal\Logging;

use Doctrine\DBAL\Logging\DebugStack;

use function array_shift;
use function debug_backtrace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

final class BacktraceLogger extends DebugStack
{
    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        parent::startQuery($sql, $params, $types);

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip first since it's always the current method
        array_shift($backtrace);

        $this->queries[$this->currentQuery]['backtrace'] = $backtrace;
    }
}
