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
        "doctrine/doctrine-bundle": ">=2.1"
        # ..
    }

### Routing

If you're using the [WebProfilerBundle](https://github.com/symfony/WebProfilerBundle) import the routes into your dev environment:

 .. configuration-block::

    .. code-block:: yaml

        # app/config/routing.yml
        _profiler_doctrine:
            resource: "@DoctrineBundle/Resources/config/routing/profiler.xml"
            prefix:   /_profiler

    .. code-block:: xml

        <!-- app/config/routing.xml -->
        <?xml version="1.0" encoding="UTF-8" ?>

        <routes xmlns="http://symfony.com/schema/routing"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://symfony.com/schema/routing http://symfony.com/schema/routing/routing-1.0.xsd">

            <import resource="@DoctrineBundle/Resources/config/routing/profiler.xml" prefix="/_profiler" />
        </routes>

    .. code-block:: php

        // app/config/routing.php
        use Symfony\Component\Routing\RouteCollection;

        $collection = new RouteCollection();
        $collection->addCollection($loader->import("@DoctrineBundle/Resources/config/routing/profiler.xml"), '/_profiler');

        return $collection;
