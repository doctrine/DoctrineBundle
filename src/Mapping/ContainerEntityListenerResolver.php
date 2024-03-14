<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;
use function trim;

/** @final */
class ContainerEntityListenerResolver implements EntityListenerServiceResolver
{
    private ContainerInterface $container;

    /** @var object[] Map to store entity listener instances. */
    private array $instances = [];

    /** @var string[] Map to store registered service ids */
    private array $serviceIds = [];

    /** @param ContainerInterface $container a service locator for listeners */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function clear($className = null): void
    {
        if ($className === null) {
            $this->instances = [];

            return;
        }

        $className = $this->normalizeClassName($className);

        unset($this->instances[$className]);
    }

    /**
     * {@inheritDoc}
     */
    public function register($object): void
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException(sprintf('An object was expected, but got "%s".', gettype($object)));
        }

        $className = $this->normalizeClassName(get_class($object));

        $this->instances[$className] = $object;
    }

    /**
     * {@inheritDoc}
     */
    public function registerService($className, $serviceId)
    {
        $this->serviceIds[$this->normalizeClassName($className)] = $serviceId;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve($className): object
    {
        $className = $this->normalizeClassName($className);

        if (! isset($this->instances[$className])) {
            if (isset($this->serviceIds[$className])) {
                $this->instances[$className] = $this->resolveService($this->serviceIds[$className]);
            } else {
                $this->instances[$className] = new $className();
            }
        }

        return $this->instances[$className];
    }

    private function resolveService(string $serviceId): object
    {
        if (! $this->container->has($serviceId)) {
            throw new RuntimeException(sprintf('There is no service named "%s"', $serviceId));
        }

        return $this->container->get($serviceId);
    }

    private function normalizeClassName(string $className): string
    {
        return trim($className, '\\');
    }
}
