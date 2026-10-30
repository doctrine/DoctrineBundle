<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Profiling;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadDataHolder;
use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadDetector;
use Doctrine\Bundle\DoctrineBundle\Profiling\LazyLoadListener;
use Doctrine\Bundle\DoctrineBundle\Tests\Profiling\Fixtures\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Tests\Profiling\Fixtures\Entity\Post;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

use function interface_exists;

final class LazyLoadListenerTest extends TestCase
{
    public function testDoesNotRecordEntitiesLoadedByARegularQuery(): void
    {
        [$entityManager, $holder, $authorId] = $this->createFixtures();

        $entityManager->clear();
        $holder->reset();

        $entityManager->find(Author::class, $authorId);

        self::assertSame([], $holder->getLazyLoads());
    }

    public function testRecordsTheEntityLoadedByALazyCollection(): void
    {
        [$entityManager, $holder, $authorId] = $this->createFixtures();

        $entityManager->clear();
        $holder->reset();

        $author = $entityManager->find(Author::class, $authorId);
        self::assertInstanceOf(Author::class, $author);

        $author->posts->count();

        self::assertSame(
            [
                [
                    'kind' => LazyLoadDetector::COLLECTION,
                    'classes' => [Post::class => 2],
                    'entities' => 2,
                    'connection' => null,
                    'query' => null,
                ],
            ],
            $holder->getLazyLoads(),
        );
    }

    public function testRecordsTheEntityLoadedByALazyProxy(): void
    {
        [$entityManager, $holder, , $postId] = $this->createFixtures();

        $entityManager->clear();
        $holder->reset();

        $post = $entityManager->find(Post::class, $postId);
        self::assertInstanceOf(Post::class, $post);
        self::assertNotNull($post->author);

        self::assertSame('Ada', $post->author->name);

        self::assertSame(
            [
                [
                    'kind' => LazyLoadDetector::SINGLE,
                    'classes' => [Author::class => 1],
                    'entities' => 1,
                    'connection' => null,
                    'query' => null,
                ],
            ],
            $holder->getLazyLoads(),
        );
    }

    /** @return array{EntityManager, LazyLoadDataHolder, int|null, int|null} */
    private function createFixtures(): array
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('doctrine/orm is not installed.');
        }

        $holder = new LazyLoadDataHolder();

        $dbalConfig = new DbalConfiguration();
        $dbalConfig->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $dbalConfig);

        $entityManager = new EntityManager($connection, $this->createOrmConfiguration());
        $entityManager->getEventManager()->addEventListener([Events::postLoad], new LazyLoadListener($holder, new BacktraceDebugDataHolder([])));

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([
            $entityManager->getClassMetadata(Author::class),
            $entityManager->getClassMetadata(Post::class),
        ]);

        $author       = new Author();
        $author->name = 'Ada';

        $post         = new Post();
        $post->title  = 'Post';
        $post->author = $author;

        $secondPost         = new Post();
        $secondPost->title  = 'Second post';
        $secondPost->author = $author;

        $entityManager->persist($author);
        $entityManager->persist($post);
        $entityManager->persist($secondPost);
        $entityManager->flush();

        return [$entityManager, $holder, $author->id, $post->id];
    }

    private function createOrmConfiguration(): OrmConfiguration
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);

        return $config;
    }
}
