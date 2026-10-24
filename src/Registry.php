<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
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
     * @param string[] $connections
     * @param string[] $entityManagers
     */
    public function __construct(Container $container, array $connections, array $entityManagers, string $defaultConnection, string $defaultEntityManager)
    {
        $this->container = $container;

        parent::__construct('ORM', $connections, $entityManagers, $defaultConnection, $defaultEntityManager, Proxy::class);
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
}
