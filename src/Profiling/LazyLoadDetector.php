<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Profiling;

use function debug_backtrace;
use function in_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

/**
 * Recognises an entity loaded by the ORM while initializing a lazy proxy or a
 * lazy collection, from the call stack captured by the postLoad listener.
 *
 * The ORM exposes no hook for lazy loading, so the frames of its internals are
 * the only signal available. They are matched by name, which ties this class to
 * the ORM implementation; the integration test runs a real lazy load so that a
 * change there is noticed.
 *
 * @internal
 *
 * @phpstan-type LazyLoad array{kind: self::SINGLE|self::COLLECTION, load: object|null}
 */
final class LazyLoadDetector
{
    public const string COLLECTION = 'collection';
    public const string SINGLE     = 'single';

    private const int MAX_DEPTH = 50;

    private const string ORM_PROXY_FACTORY         = 'Doctrine\ORM\Proxy\ProxyFactory';
    private const string ORM_PERSISTENT_COLLECTION = 'Doctrine\ORM\PersistentCollection';
    private const string ORM_UNIT_OF_WORK          = 'Doctrine\ORM\UnitOfWork';

    /**
     * Methods of PersistentCollection that fetch rows and hydrate entities.
     * An extra-lazy operation that only queries information, such as count()
     * or containsKey(), hydrates nothing and never reaches the listener.
     * Methods such as forAll() or map() run a user callback that may issue an
     * unrelated query, which is not a lazy load.
     */
    private const array COLLECTION_LOADING_METHODS = [
        'doInitialize',
        'first',
        'get',
        'matching',
        'slice',
    ];

    /** @return LazyLoad|null */
    public static function detect(): array|null
    {
        // The common case is not a lazy load: keep the backtrace light until
        // one is found. Objects are only needed to find the loaded collection.
        if (self::detectInTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_DEPTH)) === null) {
            return null;
        }

        return self::detectInTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, self::MAX_DEPTH));
    }

    /**
     * @param list<array{class?: string, function?: string, object?: object}> $trace
     *
     * @return LazyLoad|null
     */
    public static function detectInTrace(array $trace): array|null
    {
        $lazyFrameIndex = null;
        $kind           = null;

        foreach ($trace as $index => $frame) {
            $kind = self::kindOf($frame);
            if ($kind === null) {
                continue;
            }

            $lazyFrameIndex = $index;

            break;
        }

        if ($lazyFrameIndex === null || $kind === null) {
            return null;
        }

        return [
            'kind' => $kind,
            'load' => self::findLoad($trace, $lazyFrameIndex, $kind),
        ];
    }

    /**
     * Returns the collection being initialized, which is the same object for
     * every entity hydrated by that load. A single entity load needs no
     * grouping.
     *
     * @param list<array{class?: string, object?: object}> $trace
     */
    private static function findLoad(array $trace, int $lazyFrameIndex, string $kind): object|null
    {
        if ($kind !== self::COLLECTION) {
            return null;
        }

        foreach ($trace as $index => $frame) {
            if ($index < $lazyFrameIndex || ($frame['class'] ?? null) !== self::ORM_PERSISTENT_COLLECTION) {
                continue;
            }

            return $frame['object'] ?? null;
        }

        return null;
    }

    /**
     * @param array{class?: string, function?: string} $frame
     *
     * @return self::SINGLE|self::COLLECTION|null
     */
    private static function kindOf(array $frame): string|null
    {
        $class    = $frame['class'] ?? null;
        $function = $frame['function'] ?? null;

        if ($class === self::ORM_PROXY_FACTORY) {
            return self::SINGLE;
        }

        if ($class === self::ORM_UNIT_OF_WORK && $function === 'loadCollection') {
            return self::COLLECTION;
        }

        if ($class === self::ORM_PERSISTENT_COLLECTION && in_array($function, self::COLLECTION_LOADING_METHODS, true)) {
            return self::COLLECTION;
        }

        return null;
    }
}
