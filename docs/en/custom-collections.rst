Custom Collections
==================

Doctrine ORM can expose a to-many association through a `custom collection
class`_, which implements ``Doctrine\ORM\Collection\DelegateCollection``. When
the ORM loads an entity, it creates the custom collection by calling its
constructor without arguments, so a constructor that declares dependencies
fails. When the collection class is a service, the bundle makes the ORM get
it from the service container instead, with its dependencies.

.. note::

    Custom collections require Doctrine ORM >= 3.9 and DoctrineBundle >= 3.5.

Injecting Services into a Collection
------------------------------------

Declare the dependencies of the collection in its constructor, in a class that
is registered as a service with autoconfiguration, like the classes of
``src/`` in a Symfony application:

.. code-block:: php

    // src/Collection/DocumentList.php
    namespace App\Collection;

    use App\Entity\Document;
    use App\Tenant\TenantContext;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use Doctrine\ORM\Collection\DelegateCollection;
    use Traversable;

    final class DocumentList implements DelegateCollection
    {
        public function __construct(
            private readonly TenantContext $tenant,
            private Collection $collection = new ArrayCollection(),
        ) {
        }

        public function getIterator(): Traversable
        {
            return $this->collection
                ->filter(fn (Document $document) => $document->getTenant() === $this->tenant->getCurrent())
                ->getIterator();
        }
    }

The association is mapped like any other custom collection:

.. code-block:: php

    // src/Entity/Folder.php
    namespace App\Entity;

    use App\Collection\DocumentList;
    use Doctrine\ORM\Mapping as ORM;

    #[ORM\Entity]
    class Folder
    {
        #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'folder')]
        private DocumentList $documents;

        // ...
    }

Each time the ORM creates the collection of an entity, for instance when it
loads the entity, it asks the container for a ``DocumentList``. The container
autowires ``$tenant``, then the ORM puts the managed collection in the
``$collection`` property. The service is not shared, so each entity gets its
own instance.

Autoconfiguration tags every service that implements ``DelegateCollection``,
so a collection without dependencies also comes from the container, which calls
its constructor without arguments, as the ORM does. A collection that is not a
service is still created by the ORM.

As every collection service is kept in the container, the container must be able
to autowire its constructor: a collection whose constructor requires an
argument that is not a service, such as ``array $items``, makes the compilation
of the container fail, even if no entity uses it. The ORM could not create such
a collection anyway.

.. warning::

    The ``config/services.yaml`` file of applications created before Symfony
    7.3 excludes ``src/Entity/``: a collection stored next to the entities is
    not a service there, and loading an entity throws an exception that asks to
    register it as a service. Store the collection in another directory,
    ``src/Collection/`` for instance, or register it explicitly:

    .. code-block:: yaml

        # config/services.yaml
        services:
            App\Entity\DocumentList: ~

.. note::

    The container only builds the collections of the entities that the ORM
    loads or refreshes: a new entity creates its collection itself, and must
    give it its dependencies.

Registering a Collection as a Service
-------------------------------------

Autoconfiguration adds the ``doctrine.orm.delegate_collection`` tag to the
collection services. When autoconfiguration is disabled, add the tag yourself.
The bundle makes every tagged service non-shared, and finds the service of a
collection by its class:

.. configuration-block::

    .. code-block:: yaml

        # config/services.yaml
        services:
            App\Collection\DocumentList:
                tags:
                    - { name: doctrine.orm.delegate_collection }

    .. code-block:: php

        // config/services.php
        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        use App\Collection\DocumentList;

        return static function (ContainerConfigurator $container): void {
            $container->services()
                ->set(DocumentList::class)
                ->tag('doctrine.orm.delegate_collection');
        };

Customizing the Collection Factory
----------------------------------

The collections are created by the ``Doctrine\ORM\Collection\DelegateCollectionFactory``
of the entity manager, which is the
``doctrine.orm.container_delegate_collection_factory`` service on every entity
manager. To create some collections yourself, decorate this service: your
factory handles the classes it needs, and hands the others over to the inner
factory, which keeps creating the collection services:

.. code-block:: php

    // src/Collection/CollectionFactory.php
    namespace App\Collection;

    use Doctrine\ORM\Collection\DelegateCollection;
    use Doctrine\ORM\Collection\DelegateCollectionFactory;

    final class CollectionFactory implements DelegateCollectionFactory
    {
        public function __construct(
            private readonly DelegateCollectionFactory $inner,
        ) {
        }

        public function create(string $collectionClass): DelegateCollection
        {
            if ($collectionClass === LegacyList::class) {
                return LegacyList::empty();
            }

            return $this->inner->create($collectionClass);
        }
    }

.. configuration-block::

    .. code-block:: yaml

        # config/services.yaml
        services:
            App\Collection\CollectionFactory:
                decorates: doctrine.orm.container_delegate_collection_factory
                arguments: ['@.inner']

    .. code-block:: php

        // config/services.php
        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        use App\Collection\CollectionFactory;

        return static function (ContainerConfigurator $container): void {
            $container->services()
                ->set(CollectionFactory::class)
                ->decorate('doctrine.orm.container_delegate_collection_factory')
                ->args([service('.inner')]);
        };

Your factory receives every class before the container, including the classes
of the collection services.

To use another factory on a single entity manager, set its service id in the
``delegate_collection_factory`` option. This entity manager then ignores the
collection services, unless your factory calls the
``doctrine.orm.container_delegate_collection_factory`` service:

.. code-block:: yaml

    # config/packages/doctrine.yaml
    doctrine:
        orm:
            entity_managers:
                reporting:
                    delegate_collection_factory: App\Collection\ReportingCollectionFactory

.. _`custom collection class`: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/association-mapping.html#custom-collection-classes
