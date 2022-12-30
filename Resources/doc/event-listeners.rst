Event Listeners
===============

In opposite to :doc:`Entity Listeners </entity-listeners>`, Event listeners
are services that listen for all entities in your application.

See https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#implementing-event-listeners
for more info on event listeners.

To register a service to act as an event listener you have to tag it with the
``doctrine.event_listener`` tag:

Starting with Doctrine bundle 2.8, you can use the ``AsDoctrineListener``
attribute to tag the service.

.. configuration-block::

    .. code-block:: php-attributes

        // src/App/EventListener/SearchIndexer.php
        namespace App\EventListener;

        use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
        use Doctrine\ORM\Event\LifecycleEventArgs;

        #[AsDoctrineListener('postPersist'/*, 500, 'default'*/)]
        class SearchIndexer
        {
            public function postPersist(LifecycleEventArgs $event): void
            {
                // ...
            }
        }

    .. code-block:: yaml

        # config/services.yaml
        services:
            # ...

            App\EventListener\SearchIndexer:
                tags:
                    -
                        name: 'doctrine.event_listener'
                        # this is the only required option for the lifecycle listener tag
                        event: 'postPersist'

                        # listeners can define their priority in case multiple subscribers or listeners are associated
                        # to the same event (default priority = 0; higher numbers = listener is run earlier)
                        priority: 500

                        # you can also restrict listeners to a specific Doctrine connection
                        connection: 'default'

    .. code-block:: xml

        <!-- config/services.xml -->
        <?xml version="1.0" encoding="UTF-8" ?>
        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:doctrine="http://symfony.com/schema/dic/doctrine">
            <services>
                <!-- ... -->

                <!--
                    * 'event' is the only required option that defines the lifecycle listener
                    * 'priority': used when multiple subscribers or listeners are associated to the same event
                    *             (default priority = 0; higher numbers = listener is run earlier)
                    * 'connection': restricts the listener to a specific Doctrine connection
                -->
                <service id="App\EventListener\SearchIndexer">
                    <tag name="doctrine.event_listener"
                        event="postPersist"
                        priority="500"
                        connection="default"/>
                </service>
            </services>
        </container>

    .. code-block:: php

        // config/services.php
        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        use App\EventListener\SearchIndexer;

        return static function (ContainerConfigurator $configurator) {
            $services = $configurator->services();

            // listeners are applied by default to all Doctrine connections
            $services->set(SearchIndexer::class)
                ->tag('doctrine.event_listener', [
                    // this is the only required option for the lifecycle listener tag
                    'event' => 'postPersist',

                    // listeners can define their priority in case multiple subscribers or listeners are associated
                    // to the same event (default priority = 0; higher numbers = listener is run earlier)
                    'priority' => 500,

                    // you can also restrict listeners to a specific Doctrine connection
                    'connection' => 'default',
                ])
            ;
        };
