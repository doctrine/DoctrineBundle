<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Mapping\EntityListenerServiceResolver;

class CustomEntityListenerServiceResolver implements EntityListenerServiceResolver
{
    /** @var EntityListenerServiceResolver */
    private $resolver;

    public function __construct(EntityListenerServiceResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($className = null): void
    {
        $this->resolver->clear($className);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($className)
    {
        return $this->resolver->resolve($className);
    }

    /**
     * {@inheritdoc}
     */
    public function register($object): void
    {
        $this->resolver->register($object);
    }

    /**
     * {@inheritdoc}
     */
    public function registerService($className, $serviceId): void
    {
        $this->resolver->registerService($className, $serviceId);
    }
}
