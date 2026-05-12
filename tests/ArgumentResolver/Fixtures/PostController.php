<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use Symfony\Component\HttpFoundation\JsonResponse;

class PostController
{
    public function __invoke(Post $post): JsonResponse
    {
        return new JsonResponse(['id' => $post->id, 'title' => $post->title]);
    }
}
