<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\ORM\ORMException;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Contracts\Service\ResetInterface;
use function interface_exists;

/**
 * References all Doctrine connections and entity managers in a given Container.
 */
class Registry extends ManagerRegistry implements ResetInterface
{
    /**
     * @param string[] $connections
     * @param string[] $entityManagers
     * @param string   $defaultConnection
     * @param string   $defaultEntityManager
     */
    public function __construct(ContainerInterface $container, array $connections, array $entityManagers, $defaultConnection, $defaultEntityManager)
    {
        $this->container = $container;

        parent::__construct('ORM', $connections, $entityManagers, $defaultConnection, $defaultEntityManager, 'Doctrine\ORM\Proxy\Proxy');
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
            try {
                return $this->getManager($name)->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {
            }
        }

        throw ORMException::unknownEntityNamespace($alias);
    }

    public function reset() : void
    {
        if (! interface_exists(LazyLoadingInterface::class)) {
            return;
        }

        foreach (array_keys($this->getManagerNames()) as $managerName) {
            $this->resetManager($managerName);
        }
    }
}
