<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Collection;

use Doctrine\ORM\Collection\DefaultDelegateCollectionFactory;
use Doctrine\ORM\Collection\DelegateCollection;
use Doctrine\ORM\Collection\DelegateCollectionFactory;
use Doctrine\ORM\Exception\UninstantiableCollection;
use LogicException;
use Psr\Container\ContainerInterface;

use function sprintf;

/**
 * Fetches custom collections from the container or falls back to normal creation.
 *
 * @internal
 */
final class ContainerDelegateCollectionFactory implements DelegateCollectionFactory
{
    /** @param ContainerInterface $container A service locator containing the collections, indexed by class */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DelegateCollectionFactory $default = new DefaultDelegateCollectionFactory(),
    ) {
    }

    public function create(string $collectionClass): DelegateCollection
    {
        if ($this->container->has($collectionClass)) {
            return $this->container->get($collectionClass);
        }

        try {
            return $this->default->create($collectionClass);
        } catch (UninstantiableCollection $e) {
            // The ORM advises Configuration::setDelegateCollectionFactory(), which the bundle already calls
            throw new LogicException(sprintf(
                'The custom collection "%s" cannot be created without arguments. Either register it as a service, for instance by moving it out of a directory excluded from the service definitions such as "src/Entity/", or create it in a factory decorating the "doctrine.orm.container_delegate_collection_factory" service.',
                $collectionClass,
            ), 0, $e);
        }
    }
}
