<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Exception;

use RuntimeException;

/** Thrown by the N+1 guard when a statement is replayed with too many different parameter sets. */
final class NPlusOneQueriesDetected extends RuntimeException
{
}
