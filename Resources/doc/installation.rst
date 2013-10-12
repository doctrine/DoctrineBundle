Doctrine Bundle
===============

Doctrine DBAL & ORM Bundle for the Symfony Framework.

Because Symfony 2 does not want to force or suggest a specific persistence solutions on the users
this bundle was removed from the core of the Symfony 2 framework. Doctrine2 will still be a major player
in the Symfony world and the bundle is maintained by developers in the Doctrine and Symfony communities.

IMPORTANT: This bundle is developed for Symfony 2.1 and up. For Symfony 2.0 applications the DoctrineBundle
is still shipped with the core Symfony repository.

Installation
------------

1. Old deps and bin/vendors way

Add the following snippets to "deps" files:

.. code-block::

    [doctrine-mongodb]
        git=http://github.com/doctrine/dbal.git

    [doctrine-mongodb-odm]
        git=http://github.com/doctrine/doctrine2.git

    [DoctrineBundle]
        git=http://github.com/doctrine/DoctrineBundle.git
        target=/bundles/Doctrine/Bundle/DoctrineBundle

2. Composer

Add the following dependencies to your projects composer.json file:

.. code-block::

    "require": {
        # ..
        "doctrine/doctrine-bundle": "~1.2"
        # ..
    }

