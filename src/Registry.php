<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Proxy;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\Service\ResetInterface;

use function assert;
use function method_exists;

/**
 * References all Doctrine connections and entity managers in a given Container.
 */
class Registry extends ManagerRegistry implements ResetInterface
{
    /**
     * @param string[]                $connections
     * @param string[]                $entityManagers
     * @param ContainerInterface|null $metadataDrivers The metadata drivers of the entity managers, by manager name, used to find the manager of a class without initializing lazy managers
     */
    public function __construct(
        Container $container,
        array $connections,
        array $entityManagers,
        string $defaultConnection,
        string $defaultEntityManager,
        private readonly ContainerInterface|null $metadataDrivers = null,
    ) {
        $this->container = $container;

        parent::__construct('ORM', $connections, $entityManagers, $defaultConnection, $defaultEntityManager, Proxy::class);
    }

    public function getManagerForClass(string $class): ObjectManager|null
    {
        if ($this->metadataDrivers === null) {
            return parent::getManagerForClass($class);
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAnonymous() || $reflection->implementsInterface(Proxy::class)) {
            return parent::getManagerForClass($class);
        }

        foreach ($this->getManagerNames() as $name => $serviceId) {
            if ($this->isTransient($class, $name, $serviceId)) {
                continue;
            }

            $manager = $this->getService($serviceId);
            assert($manager instanceof ObjectManager);

            return $manager;
        }

        return null;
    }

    public function reset(): void
    {
        foreach ($this->getManagerNames() as $managerName => $serviceId) {
            $this->resetOrClearManager($managerName, $serviceId);
        }
    }

    private function resetOrClearManager(string $managerName, string $serviceId): void
    {
        if (! $this->container->initialized($serviceId)) {
            return;
        }

        $manager = $this->container->get($serviceId);

        assert($manager instanceof EntityManagerInterface);

        // Determine if the version of symfony/dependency-injection is >= 7.3
        /** @phpstan-ignore function.alreadyNarrowedType */
        $sfNativeLazyObjects = method_exists('Symfony\Component\DependencyInjection\ContainerBuilder', 'findTaggedResourceIds');

        if (! $sfNativeLazyObjects) {
            if (! $manager instanceof LazyObjectInterface || $manager->isOpen()) {
                $manager->clear();

                return;
            }
        } else {
            $r = new ReflectionClass($manager);
            if ($r->isUninitializedLazyObject($manager)) {
                return;
            }

            if ($manager->isOpen()) {
                $manager->clear();

                return;
            }
        }

        $this->resetManager($managerName);
    }

    /** @param class-string $class */
    private function isTransient(string $class, string $name, string $serviceId): bool
    {
        if ($this->metadataDrivers?->has($name) && $this->isUninitialized($serviceId)) {
            $driver = $this->metadataDrivers->get($name);
            assert($driver instanceof MappingDriver);

            return $driver->isTransient($class);
        }

        $manager = $this->getService($serviceId);
        assert($manager instanceof ObjectManager);

        return $manager->getMetadataFactory()->isTransient($class);
    }

    private function isUninitialized(string $serviceId): bool
    {
        if (! $this->container->initialized($serviceId)) {
            return true;
        }

        $manager = $this->container->get($serviceId);

        if ($manager instanceof LazyObjectInterface) {
            return ! $manager->isLazyObjectInitialized();
        }

        return (new ReflectionClass($manager))->isUninitializedLazyObject($manager);
    }
}
