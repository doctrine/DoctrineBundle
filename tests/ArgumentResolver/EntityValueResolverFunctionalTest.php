<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver;

use Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures\EntityValueResolverFunctionalKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures\Post;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Component\HttpFoundation\Request;

use function assert;
use function interface_exists;
use function json_decode;
use function restore_exception_handler;

/**
 * Regression coverage for the /posts/{post} + Post $post happy path resolved by
 * Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver. The tag priority
 * configured in config/orm.php (and the priorities of FrameworkBundle's own
 * value resolvers) must stay compatible so that the entity argument is still
 * populated from the route placeholder.
 */
#[RequiresMethod(EntityValueResolver::class, '__construct')]
class EntityValueResolverFunctionalTest extends TestCase
{
    #[IgnoreDeprecations]
    public function testEntityArgumentResolvedFromRoutePlaceholder(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new EntityValueResolverFunctionalKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        $em        = $container->get('doctrine.orm.default_entity_manager');
        assert($em instanceof EntityManagerInterface);

        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Post::class)]);

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

        $kernel->shutdown();
        restore_exception_handler();
    }
}
