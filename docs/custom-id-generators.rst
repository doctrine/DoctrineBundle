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
