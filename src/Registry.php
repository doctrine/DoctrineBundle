<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Proxy;
use ProxyManager\Proxy\LazyLoadingInterface;
use ReflectionClass;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_keys;
use function assert;
use function method_exists;

use const PHP_VERSION_ID;

/**
 * References all Doctrine connections and entity managers in a given Container.
 */
class Registry extends ManagerRegistry implements ResetInterface
{
    /**
     * @param string[]              $connections
     * @param string[]              $entityManagers
     * @param array<string, string> $decoratedManagers Inner service id of each decorated entity manager, keyed by its service id
     */
    public function __construct(
        Container $container,
        array $connections,
        array $entityManagers,
        string $defaultConnection,
        string $defaultEntityManager,
        private readonly array $decoratedManagers = [],
    ) {
        $this->container = $container;

        parent::__construct('ORM', $connections, $entityManagers, $defaultConnection, $defaultEntityManager, Proxy::class);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @see Configuration::getEntityNamespace
     *
     * @param string $alias The alias
     *
     * @return string The full namespace
     */
    public function getAliasNamespace($alias)
    {
        foreach (array_keys($this->getManagers()) as $name) {
            $objectManager = $this->getManager($name);

            if (! $objectManager instanceof EntityManagerInterface) {
                continue;
            }

            try {
                /** @phpstan-ignore method.notFound (ORM < 3 specific) */
                return $objectManager->getConfiguration()->getEntityNamespace($alias);
            /* @phpstan-ignore class.notFound */
            } catch (ORMException) {
            }
        }

        /* @phpstan-ignore class.notFound */
        throw ORMException::unknownEntityNamespace($alias);
    }

    public function reset(): void
    {
        foreach ($this->getManagerNames() as $managerName => $serviceId) {
            $this->resetOrClearManager($managerName, $serviceId);
        }
    }

    /** @param string $name */
    protected function resetService($name): void
    {
        // Resetting a decorator would only rebuild it around the very same manager instance
        parent::resetService($this->decoratedManagers[$name] ?? $name);
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

        if (PHP_VERSION_ID < 80400 || ! $sfNativeLazyObjects) {
            // A decorator is not a lazy object: check the manager it decorates, which is what resetService() resets
            $innerManager = isset($this->decoratedManagers[$serviceId]) ? $this->container->get($this->decoratedManagers[$serviceId]) : $manager;

            if ((! $innerManager instanceof LazyLoadingInterface && ! $innerManager instanceof LazyObjectInterface) || $manager->isOpen()) {
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
