Doctrine Console
================

Some Doctrine packages such as ``doctrine/dbal`` and ``doctrine/orm``
provide useful console commands to interact with your database and your
entities:

- `Doctrine DBAL Commands
  <https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/cli-tools.html#cli-tools>`_
- `Doctrine ORM Commands
  <https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/tools.html#doctrine-console>`_

This bundle automatically registers those commands, but it renames them
in the process so as to group them under the ``doctrine:`` namespace.

Note that the bundle also provide some additional commands that are not
part of the aforementioned packages.

Command Name Mapping
--------------------

The following table shows the mapping between the original Doctrine command
names and the names used by this bundle:

+--------------------------------------------+---------------------------------------+
| Bundle Command Name                        | Original Doctrine Command Name        |
+============================================+=======================================+
| ``doctrine:cache:clear-metadata``          | ``orm:clear-cache:metadata``          |
+--------------------------------------------+---------------------------------------+
| ``doctrine:cache:clear-query``             | ``orm:clear-cache:query``             |
+--------------------------------------------+---------------------------------------+
| ``doctrine:cache:clear-result``            | ``orm:clear-cache:result``            |
+--------------------------------------------+---------------------------------------+
| ``doctrine:cache:clear-collection-region`` | ``orm:clear-cache:region:collection`` |
+--------------------------------------------+---------------------------------------+
| ``doctrine:cache:clear-entity-region``     | ``orm:clear-cache:region:entity``     |
+--------------------------------------------+---------------------------------------+
| ``doctrine:cache:clear-query-region``      | ``orm:clear-cache:region:query``      |
+--------------------------------------------+---------------------------------------+
| ``doctrine:schema:create``                 | ``orm:schema-tool:create``            |
+--------------------------------------------+---------------------------------------+
| ``doctrine:schema:drop``                   | ``orm:schema-tool:drop``              |
+--------------------------------------------+---------------------------------------+
| ``doctrine:schema:update``                 | ``orm:schema-tool:update``            |
+--------------------------------------------+---------------------------------------+
| ``doctrine:mapping:info``                  | ``orm:info``                          |
+--------------------------------------------+---------------------------------------+
| ``doctrine:mapping:describe``              | ``orm:mapping:describe``              |
+--------------------------------------------+---------------------------------------+
| ``doctrine:query:dql``                     | ``orm:run-dql``                       |
+--------------------------------------------+---------------------------------------+
| ``doctrine:schema:validate``               | ``orm:validate-schema``               |
+--------------------------------------------+---------------------------------------+

Additionally, the bundle provides the following commands:

- ``doctrine:database:create``
- ``doctrine:database:drop``

To get a list of all available Doctrine commands, run:

.. code-block:: console

    php bin/console list doctrine

It is possible to get help on any specific command. For example,
to get help on the ``doctrine:schema:update`` command, run:

.. code-block:: console

    php bin/console doctrine:schema:update --help
