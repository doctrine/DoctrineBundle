<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

class TaggedProviderPostController
{
    public function __invoke(
        #[MapEntity(expr: 'repository.findOneBy({"title": current_title()})')]
        Post $post,
    ): JsonResponse {
        return new JsonResponse(['id' => $post->id, 'title' => $post->title]);
    }
}
