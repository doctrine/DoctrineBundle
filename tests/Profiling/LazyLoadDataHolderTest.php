<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Profiling;

use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadDataHolder;
use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadDetector;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LazyLoadDataHolderTest extends TestCase
{
    public function testGroupsTheEntitiesHydratedByTheSameLoad(): void
    {
        $holder     = new LazyLoadDataHolder();
        $collection = new stdClass();

        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, $collection, 'default', 3);
        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, $collection, 'default', 4);

        self::assertSame(
            [
                [
                    'kind' => LazyLoadDetector::COLLECTION,
                    'classes' => ['App\Entity\Post' => 2],
                    'entities' => 2,
                    'connection' => 'default',
                    'query' => 3,
                ],
            ],
            $holder->getLazyLoads(),
        );
        self::assertSame(2, $holder->getCount());
    }

    public function testKeepsTheClassesOfTheSameLoad(): void
    {
        $holder     = new LazyLoadDataHolder();
        $collection = new stdClass();

        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, $collection);
        $holder->record('App\Entity\SpecialPost', LazyLoadDetector::COLLECTION, $collection);

        self::assertSame(
            [['App\Entity\Post' => 1, 'App\Entity\SpecialPost' => 1]],
            [$holder->getLazyLoads()[0]['classes']],
        );
    }

    public function testKeepsSeparateLoadsApart(): void
    {
        $holder = new LazyLoadDataHolder();

        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, new stdClass());
        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, new stdClass());

        self::assertCount(2, $holder->getLazyLoads());
        self::assertSame(2, $holder->getCount());
    }

    public function testDoesNotGroupToOneLoads(): void
    {
        $holder = new LazyLoadDataHolder();

        $holder->record('App\Entity\Author', LazyLoadDetector::SINGLE);
        $holder->record('App\Entity\Author', LazyLoadDetector::SINGLE);

        self::assertCount(2, $holder->getLazyLoads());
        self::assertSame(2, $holder->getCount());
    }

    public function testAcceptsALazyLoadWithoutQuery(): void
    {
        $holder = new LazyLoadDataHolder();
        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION);

        self::assertSame(
            [
                [
                    'kind' => LazyLoadDetector::COLLECTION,
                    'classes' => ['App\Entity\Post' => 1],
                    'entities' => 1,
                    'connection' => null,
                    'query' => null,
                ],
            ],
            $holder->getLazyLoads(),
        );
    }

    public function testResetForgetsTheLazyLoads(): void
    {
        $holder = new LazyLoadDataHolder();
        $holder->record('App\Entity\Post', LazyLoadDetector::COLLECTION, new stdClass());

        $holder->reset();

        self::assertSame([], $holder->getLazyLoads());
        self::assertSame(0, $holder->getCount());
    }
}
