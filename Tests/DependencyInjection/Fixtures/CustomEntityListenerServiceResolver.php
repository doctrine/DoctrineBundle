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
    public function clear($className = null)
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
    public function register($object)
    {
        $this->resolver->register($object);
    }

    /**
     * {@inheritdoc}
     */
    public function registerService($className, $serviceId)
    {
        $this->resolver->registerService($className, $serviceId);
    }
}
