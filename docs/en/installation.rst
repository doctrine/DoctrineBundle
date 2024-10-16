Installation
============

Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the following
command to download the latest stable version of this bundle:

.. code-block:: bash

    $ composer require doctrine/doctrine-bundle

This command requires you to have Composer installed globally, as explained
in the `installation chapter`_ of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

Your bundle should be automatically enabled by Flex.
In case you don't use Flex, you'll need to manually enable the bundle by
adding the following line in the ``config/bundles.php`` file of your project::

    <?php
    // config/bundles.php

    return [
        // ...
        Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
        // ...
    ];

If you don't have a ``config/bundles.php`` file in your project, chances are that
you're using an older Symfony version. In this case, you should have an
``app/AppKernel.php`` file instead. Edit such file::

    <?php
    // app/AppKernel.php

    // ...
    class AppKernel extends Kernel
    {
        public function registerBundles()
        {
            $bundles = [
                // ...

                new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            ];

            // ...
        }

        // ...
    }

.. _`installation chapter`: https://getcomposer.org/doc/00-intro.md
