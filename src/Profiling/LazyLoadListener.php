<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Profiling;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Records the entities that the ORM loads while initializing a lazy proxy or a
 * lazy collection, so that the profiler can report them.
 *
 * The postLoad event also fires for entities loaded by a regular query, so the
 * call stack is inspected to keep only the lazy loads. This listener runs only
 * when profiling is enabled for the connection.
 *
 * @internal
 */
final class LazyLoadListener
{
    public function __construct(
        private readonly LazyLoadDataHolder $dataHolder,
        private readonly BacktraceDebugDataHolder $debugDataHolder,
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $lazyLoad = LazyLoadDetector::detect();
        if ($lazyLoad === null) {
            return;
        }

        $lastQuery = $this->debugDataHolder->getLastQuery();

        $this->dataHolder->record(
            $args->getObjectManager()->getClassMetadata($args->getObject()::class)->getName(),
            $lazyLoad['kind'],
            $lazyLoad['load'],
            $lastQuery['connection'] ?? null,
            $lastQuery['query'] ?? null,
        );
    }
}
