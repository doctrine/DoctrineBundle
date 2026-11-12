<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Profiling;

use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadDetector;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LazyLoadDetectorTest extends TestCase
{
    public function testDetectsACollectionBeingInitialized(): void
    {
        self::assertSame(
            [
                'kind' => LazyLoadDetector::COLLECTION,
                'load' => null,
            ],
            LazyLoadDetector::detectInTrace([
                ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'doInitialize'],
                ['class' => 'Doctrine\ORM\UnitOfWork', 'function' => 'loadCollection'],
                ['class' => 'App\Report', 'function' => 'build'],
            ]),
        );
    }

    public function testDetectsAnExtraLazyCollectionElement(): void
    {
        self::assertSame(
            [
                'kind' => LazyLoadDetector::COLLECTION,
                'load' => null,
            ],
            LazyLoadDetector::detectInTrace([
                ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'get'],
            ]),
        );
    }

    public function testIgnoresAnExtraLazyOperationThatHydratesNothing(): void
    {
        self::assertNull(LazyLoadDetector::detectInTrace([
            ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'count'],
        ]));
        self::assertNull(LazyLoadDetector::detectInTrace([
            ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'containsKey'],
        ]));
    }

    public function testDetectsALazyToOneAssociation(): void
    {
        self::assertSame(
            [
                'kind' => LazyLoadDetector::SINGLE,
                'load' => null,
            ],
            LazyLoadDetector::detectInTrace([
                ['class' => 'Doctrine\ORM\Proxy\ProxyFactory', 'function' => '{closure}'],
                ['class' => 'Doctrine\ORM\UnitOfWork', 'function' => 'withLazyLoading'],
                ['class' => 'App\Report', 'function' => 'build'],
            ]),
        );
    }

    public function testReturnsTheCollectionBeingLoadedToGroupItsEntities(): void
    {
        $collection = new stdClass();

        $result = LazyLoadDetector::detectInTrace([
            ['class' => 'Doctrine\ORM\UnitOfWork', 'function' => 'loadCollection'],
            ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'doInitialize', 'object' => $collection],
            ['class' => 'App\Report', 'function' => 'build'],
        ]);

        self::assertNotNull($result);
        self::assertSame($collection, $result['load']);
    }

    public function testIgnoresAQueryIssuedFromACollectionCallback(): void
    {
        self::assertNull(LazyLoadDetector::detectInTrace([
            ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'forAll'],
            ['function' => 'App\Service\Report::build'],
        ]));
    }

    public function testIgnoresATraceWithoutOrmFrames(): void
    {
        self::assertNull(LazyLoadDetector::detectInTrace([
            ['class' => 'Doctrine\DBAL\Connection', 'function' => 'executeQuery'],
            ['function' => 'App\Repository\PostRepository::find'],
        ]));
    }
}
