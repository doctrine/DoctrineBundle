<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver;

use Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures\EntityValueResolverFunctionalKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures\Post;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function assert;
use function interface_exists;
use function json_decode;

/**
 * Regression coverage for the /posts/{post} + Post $post happy path resolved by
 * Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver. The tag priority
 * configured in config/orm.php (and the priorities of FrameworkBundle's own
 * value resolvers) must stay compatible so that the entity argument is still
 * populated from the route placeholder.
 */
class EntityValueResolverFunctionalTest extends TestCase
{
    public function testEntityArgumentResolvedFromRoutePlaceholder(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new EntityValueResolverFunctionalKernel();
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $em        = $container->get('doctrine.orm.default_entity_manager');
            assert($em instanceof EntityManagerInterface);

            // SchemaTool::createSchema triggers Schema::createTable, deprecated in
            // DBAL 4 (https://github.com/doctrine/dbal/pull/7373); the DBAL 4 fluent
            // replacement (Table::editor() / Column::editor()) is unavailable on
            // DBAL 3 which this branch must still support, so a raw CREATE TABLE is
            // emitted directly through the connection. The test kernel is SQLite-
            // only, so a SQLite literal is safe here.
            $em->getConnection()->executeStatement(
                'CREATE TABLE posts (id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            );

            $post = new Post('Hello world');
            $em->persist($post);
            $em->flush();
            $em->clear();

            $response = $kernel->handle(Request::create('/posts/' . $post->id, 'GET'));

            self::assertSame(200, $response->getStatusCode());
            self::assertSame(
                ['id' => $post->id, 'title' => 'Hello world'],
                json_decode((string) $response->getContent(), true),
            );
        } finally {
            $kernel->shutdown();
        }
    }
}
