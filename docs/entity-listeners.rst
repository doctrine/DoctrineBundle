Entity Listeners
================

Entity listeners that are services must be registered with the entity listener
resolver. On top of the annotation in the entity class, you have to tag the
service with ``doctrine.orm.entity_listener`` for it to be automatically added
to the resolver. Use the (optional) ``entity_manager`` attribute to specify
which entity manager it should be registered with.

Full example:

.. configuration-block::

    .. code-block:: php-annotations

        <?php
        // User.php

        use Doctrine\ORM\Mapping as ORM;
        use App\UserListener;

        /**
         * @ORM\Entity
         * @ORM\EntityListeners({UserListener::class})
         */
        class User
        {
            // ....
        }
    
    .. code-block:: php-attributes

        <?php
        // User.php

        use Doctrine\ORM\Mapping as ORM;
        use App\UserListener;

        #[ORM\Entity]
        #[ORM\EntityListeners([UserListener::class])]
        class User
        {
            // ....
        }

.. configuration-block::

    .. code-block:: yaml

        services:
            App\UserListener:
                tags:
                    # Minimal configuration below
                    - { name: doctrine.orm.entity_listener }
                    # Or, optionally, you can give the entity manager name as below
                    #- { name: doctrine.orm.entity_listener, entity_manager: custom }

    .. code-block:: xml

        <?xml version="1.0" ?>

        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">

            <services>
                <service id="App\UserListener">
                    <!-- entity_manager attribute is optional -->
                    <tag name="doctrine.orm.entity_listener" entity_manager="custom" />
                </service>
            </services>
        </container>

Starting with doctrine/orm 2.5 and Doctrine bundle 1.5.2, instead of registering
the entity listener on the entity, you can declare all options from the service
definition:

.. configuration-block::

    .. code-block:: yaml

        services:
            App\UserListener:
                tags:
                    -
                        name: doctrine.orm.entity_listener
                        event: preUpdate
                        entity: App\Entity\User
                        # entity_manager attribute is optional
                        entity_manager: custom
                        # method attribute is optional
                        method: validateEmail

    .. code-block:: xml

        <?xml version="1.0" ?>

        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">

            <services>
                <service id="App\UserListener">
                    <!-- entity_manager attribute is optional -->
                    <!-- method attribute is optional -->
                    <tag
                        name="doctrine.orm.entity_listener" 
                        event="preUpdate"
                        entity="App\Entity\User"
                        entity_manager="custom"
                        method="validateEmail"
                    />
                </service>
            </services>
        </container>

The ``event`` attribute is required if the entity listener is not registered on
the entity. If you don't specify the ``method`` attribute, it falls back on the
subscribed event name.

Starting with Doctrine bundle 1.12, if this method does not exist but if your entity listener is invokable, it falls
back on the ``__invoke()`` method.

See also
https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#entity-listeners
for more info on entity listeners and the resolver required by Symfony.

Lazy Entity Listeners
---------------------

You can use the ``lazy`` attribute on the tag to make sure the listener services
are only instantiated when they are actually used.
    
.. configuration-block::

    .. code-block:: yaml

        services:
            App\UserListener:
                tags:
                    - { name: doctrine.orm.entity_listener, lazy: true }
                    
    .. code-block:: xml

        <?xml version="1.0" ?>

        <container xmlns="http://symfony.com/schema/dic/services"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">

            <services>
                <service id="App\UserListener">
                    <tag name="doctrine.orm.entity_listener" event="preUpdate" entity="App\Entity\User" lazy="true" />            
                </service>
            </services>
        </container>
