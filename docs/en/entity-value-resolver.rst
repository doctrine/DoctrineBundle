Entity Value Resolver Expressions
=================================

.. note::

    Registering expression language providers for the entity value resolver, and
    the security functions described below, are new in DoctrineBundle 3.4.

The ``#[MapEntity]`` attribute of Symfony's Doctrine bridge can fetch an entity
through an expression, evaluated with the ExpressionLanguage component. Besides
the request attributes and the ``repository`` variable provided by Symfony, the
bundle registers the security functions ``current_user()``, ``is_granted()``,
``is_authenticated()``, ``is_fully_authenticated()`` and ``is_remember_me()``.
``current_user()`` returns the currently authenticated user, or ``null`` when
there is none:

.. code-block:: php

    <?php
    // VisitController.php

    use App\Entity\Visit;
    use Symfony\Bridge\Doctrine\Attribute\MapEntity;

    class VisitController
    {
        public function index(
            #[MapEntity(class: Visit::class, expr: 'repository.findLatest(current_user())')]
            iterable $visits,
        ) {
            // ...
        }
    }

The functions come from the ``security.expression_language_provider`` service of
SecurityBundle, so they require Symfony 8.2 or higher, and they are only registered
when the Security component is in use. They read the security context of the current
request: in a console command resolved by
``Symfony\Bridge\Doctrine\ArgumentResolver\Console\EntityValueResolver`` there is no
such context, so evaluating them throws a ``LogicException`` rather than reporting
that nobody is authenticated.

Registering your own functions
------------------------------

Tag a service implementing ``Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface``
with ``doctrine.orm.entity_value_resolver.expression_language_provider`` to add
your own functions to those expressions:

.. code-block:: php

    <?php
    // ExpressionLanguageProvider.php

    use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
    use Symfony\Component\ExpressionLanguage\ExpressionFunction;
    use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

    #[AutoconfigureTag('doctrine.orm.entity_value_resolver.expression_language_provider')]
    class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
    {
        public function getFunctions(): array
        {
            return [
                new ExpressionFunction(
                    'current_locale',
                    static fn () => throw new \LogicException('The "current_locale" function cannot be compiled.'),
                    fn (array $variables) => $variables['request']->getLocale(),
                ),
            ];
        }
    }

The function is then available in any ``#[MapEntity]`` expression:

.. code-block:: php

    #[MapEntity(class: Article::class, expr: 'repository.findTranslated(current_locale())')]

With Symfony 8.2 and higher, the expressions of ``#[MapEntity]`` are compiled while
the cache is warmed up. A function whose compiler throws is then called through its
evaluator instead, so declaring a throwing compiler is safe: it only keeps the
expression from being fully compiled.

Version compatibility
---------------------

The ``doctrine.orm.entity_value_resolver.expression_language_provider`` tag requires
``symfony/expression-language``, which ``#[MapEntity]`` expressions already need. It
works with every version of the component the bundle supports (6.4 or higher).

The security functions are only registered when ``symfony/security-bundle`` 8.2 or
higher is installed, where the ``security.expression_language_provider`` service
exists, and when the Security component is in use. With older versions,
``#[MapEntity]`` expressions keep working, but the security functions are not
available in them.
