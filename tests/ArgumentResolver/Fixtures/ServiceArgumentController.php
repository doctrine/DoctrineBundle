<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ServiceArgumentController
{
    public function __invoke(RequestStack $requestStack): Response
    {
        return new Response((string) $requestStack->getCurrentRequest()?->getPathInfo());
    }
}
