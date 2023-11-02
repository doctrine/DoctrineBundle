<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Bundle\DoctrineBundle\Mapping\EntityListenerServiceResolver;

class CustomEntityListenerServiceResolver implements EntityListenerServiceResolver
{
    private EntityListenerServiceResolver $resolver;

    public function __construct(EntityListenerServiceResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * {@inheritDoc}
     */
    public function clear($className = null): void
    {
        $this->resolver->clear($className);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve($className): object
    {
        return $this->resolver->resolve($className);
    }

    /**
     * {@inheritDoc}
     */
    public function register($object): void
    {
        $this->resolver->register($object);
    }

    /**
     * {@inheritDoc}
     */
    public function registerService($className, $serviceId): void
    {
        $this->resolver->registerService($className, $serviceId);
    }
}
