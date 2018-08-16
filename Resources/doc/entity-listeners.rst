Entity Listeners
================

To set up an entity listener, start by creating a listener class:

.. code-block:: php

    // src/EventListener/UserListener
    namespace App\EventListener;

    use Doctrine\ORM\Event\LifecycleEventArgs;
    use App\Entity\User;

    class UserListener
    {    
        public function prePersist(User $user, LifecycleEventArgs $event)
        {
            // ...
        }
    }

Next, you have to register the class as a listener. There are two ways to
achieve this:

You can either do the registration in your entity like this:

.. code-block:: php

    // src/Entity/User.php
    namespace App\Entity\User;

    use Doctrine\ORM\Mapping as ORM;

    /**
     * @ORM\Entity
     * @ORM\EntityListeners({"App\EventListener\UserListener"})
     */
    class User
    {
        // ...
    }

This works fine, but your event listener will not be registered as a service
(even if you have ``autowire: true`` in your ``services.yaml``). So to register
it as a service, you have to add this to your ``services.yaml``:

.. configuration-block::

    .. code-block:: yaml

        services:
            App\EventListener\UserListener:
                tags:
                    - { name: doctrine.orm.entity_listener }

    .. code-block:: xml

        <?xml version="1.0" ?>

        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">

            <services>
                <service id="App\EventListener\UserListener">
                    <tag name="doctrine.orm.entity_listener" />
                </service>
            </services>
        </container>

Alternatively, you could do the entire configuration in ``services.yaml`` and
omit the ``@ORM\EntityListeners`` annotation in the entity:

.. code-block:: yaml

    services:
        App\EventListener\UserListener:
            tags:
                - { name: doctrine.orm.entity_listener, entity: App\Entity\User, event: prePersist }

To register the listener for a custom entity manager, just add the ``entity_manager`` attribute.

See also https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/events.html#entity-listeners for more info on entity listeners.

