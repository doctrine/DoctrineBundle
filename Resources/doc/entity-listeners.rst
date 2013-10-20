Entity Listeners
================

Entity listeners that are services must be registered with the entity
listener resolver. You can tag your entity listeners and they will automatically
be added to the resolver. Use the entity_manager attribute to specify which
entity manager it should be registered with. Example:


.. code-block:: yaml

    services:
        user_listener:
            class: \UserListener
            tags:
                - { name: doctrine.orm.entity_listener }
                - { name: doctrine.orm.entity_listener, entity_manager: custom }

.. code-block:: xml

    <?xml version="1.0" ?>

    <container xmlns="http://symfony.com/schema/dic/services"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">

        <services>
            <service id="user_listener" class="UserListener">
                <tag name="doctrine.orm.entity_listener" />
                <tag name="doctrine.orm.entity_listener" entity_manager="custom" />
            </service>
        </services>
    </container>
