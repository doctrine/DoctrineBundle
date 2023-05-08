Middlewares
===========

Doctrine DBAL supports middlewares. According to the `DBAL documentation`_:

    "A middleware sits in the middle between the wrapper components and the driver"

They allow to decorate the following DBAL classes:

- ``Doctrine\\DBAL\\Driver``
- ``Doctrine\\DBAL\\Driver\\Connection``
- ``Doctrine\\DBAL\\Driver\\Statement``
- ``Doctrine\\DBAL\\Driver\\Result``

Symfony, for instance, uses a middleware to harvest the queries executed
by the current page and make them available in the profiler.

.. _`DBAL documentation`: https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/architecture.html#middlewares

You can also create your own middleware. This is an example of a (very)
simple middleware that prevents database connections with the root user.
The first step is to create the middleware:

.. code-block:: php

    <?php

    namespace App\Middleware;

    use Doctrine\DBAL\Driver;
    use Doctrine\DBAL\Driver\Middleware;

    class PreventRootConnectionMiddleware implements Middleware
    {
        public function wrap(Driver $driver): Driver
        {
            return new PreventRootConnectionDriver($driver);
        }
    }

As you can see in the ``wrap`` method, the principle of a middleware is
to decorate Doctrine objects with your own objects bearing the logic you
need. Now, the ``connect`` method of the driver must be decorated in
``PreventRootConnectionDriver`` to prevent connections with the root user:

.. code-block:: php

    <?php

    namespace App\Middleware;

    use Doctrine\DBAL\Driver\Connection;
    use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
    use SensitiveParameter;

    final class PreventRootConnectionDriver extends AbstractDriverMiddleware
    {
        public function connect(array $params): Connection
        {
            if (isset($params['user']) && $params['user'] === 'root') {
                throw new \LogicException('Connecting to the database with the root user is not allowed.');
            }

            return parent::connect($params);
        }
    }

That's all ! Connection with the root user is not possible anymore. Note
that ``connect`` is not the only method you can decorate in a ``Connection``.
But thanks to the ``AbstractDriverMiddleware`` default implementation,
you only need to decorate the methods for which you want to add some logic.
Too see a more advanced example with a decoration of the ``Statement`` class,
you can look at the middleware implementation starting in the class
:class:`Symfony\\Bridge\\Doctrine\\Middleware\\Debug\\Middleware` of the
Doctrine Bridge. Decorating the ``Result`` class follows the same principle.

The middleware we've just created applies by default to all the connections.
If your application has several dbal connections, you can limit the middleware
scope to a subset of connections thanks to the ``AsMiddleware`` PHP attribute.
Let's limit our middleware to a connection named ``legacy``:

.. code-block:: php

    <?php

    namespace App\Middleware;

    use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
    use Doctrine\DBAL\Driver;
    use Doctrine\DBAL\Driver\Middleware;

    #[AsMiddleware(connections: ['legacy'])]
    class PreventRootConnectionMiddleware implements Middleware
    {
        public function wrap(Driver $driver): Driver
        {
            return new PreventRootConnectionDriver($driver);
        }
    }

All the examples presented above assume ``autoconfigure`` is enabled.
If ``autoconfigure`` is disabled, the ``doctrine.middleware`` tag must be
added to the middleware. This tag supports a ``connections`` attribute to
limit the scope of the middleware.

.. note::

    Middlewares have been introduced in version 3.2 of ``doctrine/dbal``
    and at least the 2.6 version of ``doctrine/doctrine-bundle`` is needed
    to integrate them in Symfony as shown above.

