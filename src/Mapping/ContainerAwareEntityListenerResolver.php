<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareEntityListenerResolver implements EntityListenerServiceResolver
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array Map to store entity listener instances.
     */
    private $instances = array();

    /**
     * @var array Map to store registered service ids
     */
    private $serviceIds = array();

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($className = null)
    {
        if ($className === null) {
            $this->instances = array();

            return;
        }

        $className = $this->normalizeClassName($className);

        if (isset($this->instances[$className])) {
            unset($this->instances[$className]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register($object)
    {
        if ( ! is_object($object)) {
            throw new \InvalidArgumentException(sprintf('An object was expected, but got "%s".', gettype($object)));
        }

        $className = $this->normalizeClassName(get_class($object));

        $this->instances[$className] = $object;
    }

    /**
     * {@inheritdoc}
     */
    public function registerService($className, $serviceId)
    {
        $this->serviceIds[$this->normalizeClassName($className)] = $serviceId;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($className)
    {
        $className = $this->normalizeClassName($className);

        if (!isset($this->instances[$className])) {
            if (isset($this->serviceIds[$className])) {
                $this->instances[$className] = $this->resolveService($this->serviceIds[$className]);
            } else {
                $this->instances[$className] = new $className();
            }
        }

        return $this->instances[$className];
    }

    /**
     * @param string $serviceId
     *
     * @return object
     */
    private function resolveService($serviceId)
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('There is no service named "%s"', $serviceId));
        }

        return $this->container->get($serviceId);
    }

    /**
     * @param $className
     *
     * @return string
     */
    private function normalizeClassName($className)
    {
        return trim($className, '\\');
    }
}
