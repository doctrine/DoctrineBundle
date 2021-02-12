Custom ID Generators
====================

Custom ID generators are classes that allow implementing custom logic to generate
identifiers for your entities. They extend ``Doctrine\ORM\Id\AbstractIdGenerator``
and implement the custom logic in the ``generate(EntityManager $em, $entity)``
method. Before Doctrine bundle 2.3, custom ID generators were always created
without any constructor arguments.

Starting with Doctrine bundle 2.3, the ``CustomIdGenerator`` annotation can be
used to reference any services tagged with the ``doctrine.id_generator`` tag.
If you enable autoconfiguration (which is the default most of the time), Symfony
will add this tag for you automatically if you implement your own id-generators.

When using Symfony's Doctrine bridge and Uid component 5.3 or higher, two services
are provided: ``doctrine.ulid_generator`` to generate ULIDs, and
``doctrine.uuid_generator`` to generate UUIDs.

.. code-block:: php

    <?php
    // User.php

    use Doctrine\ORM\Mapping as ORM;

    /**
     * @ORM\Entity
     */
    class User
    {
        /**
         * @Id
         * @Column(type="uuid")
         * @ORM\GeneratedValue(strategy="CUSTOM")
         * @ORM\CustomIdGenerator("doctrine.uuid_generator")
         */
        private $id;

        // ....
    }

See also
https://www.doctrine-project.org/projects/doctrine-orm/en/2.8/reference/annotations-reference.html#annref_customidgenerator
for more info about custom ID generators.

Doctrine bundle 2.3 also provides the `@ServiceGeneratedValue` annotation
that you can use instead of the `@GeneratedValue` + `@CustomIdGenerator` combo:

.. code-block:: php

    <?php
    // User.php

    use Doctrine\Bundle\DoctrineBundle\Mapping\ServiceGeneratedValue;
    use Doctrine\ORM\Mapping as ORM;

    /**
     * @ORM\Entity
     */
    class User
    {
        /**
         * @Id
         * @Column(type="integer")
         * @ServiceGeneratedValue("my_id_generator")
         */
        private $id;

        // ....
    }

If the id-generator service supports it, this annotation allows defining a method
that should be called on the service to configure it. This possibility is leveraged
by the `doctrine.uuid_generator` service to allow configuring which type of UUID
should be generated, if the defaults don't suit your needs.

E.g. ``@ServiceGeneratedValue("doctrine.uuid_generator", "randomBased")`` will
generate UUIDv4 random-based UUIDs instead of the default ones (usually UUIDv6.)

This more advanced example will populate a property by generating a name-based
UUIDv5 that would hash the return-value of ``$entity->getEmail()`` with ``some-UUID-namespace``:
``@ServiceGeneratedValue("doctrine.uuid_generator", "nameBased", "getEmail", "some-UUID-namespace")``
