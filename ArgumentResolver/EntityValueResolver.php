<?php

namespace Doctrine\Bundle\DoctrineBundle\ArgumentResolver;

use Doctrine\Bundle\DoctrineBundle\Attribute\Entity;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function array_combine;
use function array_filter;
use function array_merge;
use function count;
use function is_array;
use function method_exists;
use function sprintf;
use function strstr;

/**
 * Yields the entity matching the criteria provided in the route
 */
final class EntityValueResolver implements ArgumentValueResolverInterface
{
    /** @var ManagerRegistry */
    private $registry;
    /** @var ExpressionLanguage|null */
    private $language;
    /** @var array<string, mixed> */
    private $defaultOptions;

    /** @param array<string, mixed> $defaultOptions */
    public function __construct(ManagerRegistry $registry, ?ExpressionLanguage $expressionLanguage = null, array $defaultOptions = [])
    {
        $this->registry       = $registry;
        $this->language       = $expressionLanguage;
        $this->defaultOptions = array_merge([
            'entity_manager' => null,
            'expr' => null,
            'auto_mapping' => true,
            'mapping' => [],
            'exclude' => [],
            'strip_null' => false,
            'id' => null,
            'evict_cache' => false,
        ], $defaultOptions);
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if (count($this->registry->getManagerNames()) === 0) {
            return false;
        }

        $options = $this->getOptions($argument);
        if ($options['class'] === null) {
            return false;
        }

        // Doctrine Entity?
        $em = $this->getManager($options['entity_manager'], $options['class']);
        if ($em === null) {
            return false;
        }

        return ! $em->getMetadataFactory()->isTransient($options['class']);
    }

    /** @return object[] */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $options = $this->getOptions($argument);

        $name  = $argument->getName();
        $class = $options['class'];

        $errorMessage = null;
        if ($options['expr'] !== null) {
            $object = $this->findViaExpression($class, $request, $options['expr'], $options);

            if ($object === null) {
                $errorMessage = sprintf('The expression "%s" returned null', $options['expr']);
            }

            // find by identifier?
        } else {
            $object = $this->find($class, $request, $options, $name);
            if ($object === false) {
                // find by criteria
                $object = $this->findOneBy($class, $request, $options);
                if ($object === false) {
                    if (! $argument->isNullable()) {
                        throw new LogicException(sprintf('Unable to guess how to get a Doctrine instance from the request information for parameter "%s".', $name));
                    }

                    $object = null;
                }
            }
        }

        if ($object === null && ! $argument->isNullable()) {
            $message = sprintf('"%s" object not found by the "%s" Argument Resolver.', $class, self::class);
            if ($errorMessage) {
                $message .= ' ' . $errorMessage;
            }

            throw new NotFoundHttpException($message);
        }

        return [$object];
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if ($name === null) {
            return $this->registry->getManagerForClass($class);
        }

        return $this->registry->getManager($name);
    }

    /**
     * @param array<string, string> $options
     *
     * @return false|object|null
     */
    private function find(string $class, Request $request, array $options, string $name)
    {
        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if ($id === false || $id === null) {
            return false;
        }

        $om = $this->getManager($options['entity_manager'], $class);
        if ($options['evict_cache'] && $om instanceof EntityManagerInterface) {
            $cacheProvider = $om->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($class, $id)) {
                $cacheProvider->evictEntity($class, $id);
            }
        }

        try {
            return $om->getRepository($class)->find($id);
        } catch (NoResultException | ConversionException $e) {
            return null;
        }
    }

    /**
     * @param array<string, string> $options
     *
     * @return false|mixed|mixed[]
     */
    private function getIdentifier(Request $request, array $options, string $name)
    {
        if ($options['id'] !== null) {
            if (is_array($options['id'])) {
                $id = [];
                foreach ($options['id'] as $field) {
                    // Convert "%s_uuid" to "foobar_uuid"
                    if (strstr($field, '%s') !== false) {
                        $field = sprintf($field, $name);
                    }

                    $id[$field] = $request->attributes->get($field);
                }

                return $id;
            }

            $name = $options['id'];
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name);
        }

        if ($request->attributes->has('id') && ! $options['id']) {
            return $request->attributes->get('id');
        }

        return false;
    }

    /**
     * @param array<string, string> $options
     *
     * @return false|object|null
     */
    private function findOneBy(string $class, Request $request, array $options)
    {
        if (! $options['mapping']) {
            if (! $options['auto_mapping']) {
                return false;
            }

            $keys               = $request->attributes->keys();
            $options['mapping'] = $keys ? array_combine($keys, $keys) : [];
        }

        foreach ($options['exclude'] as $exclude) {
            unset($options['mapping'][$exclude]);
        }

        if (! $options['mapping']) {
            return false;
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if ($options['id'] && $request->attributes->get($options['id']) === null) {
            return false;
        }

        $criteria = [];
        $em       = $this->getManager($options['entity_manager'], $class);
        $metadata = $em->getClassMetadata($class);

        foreach ($options['mapping'] as $attribute => $field) {
            if (! $metadata->hasField($field) && (! $metadata->hasAssociation($field) || ! $metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options['strip_null']) {
            $criteria = array_filter($criteria, static function ($value) {
                return $value !== null;
            });
        }

        if (! $criteria) {
            return false;
        }

        try {
            return $em->getRepository($class)->findOneBy($criteria);
        } catch (NoResultException | ConversionException $e) {
            return null;
        }
    }

    /**
     * @param array<string, string> $options
     *
     * @return object|null
     */
    private function findViaExpression(string $class, Request $request, string $expression, array $options)
    {
        if ($this->language === null) {
            throw new LogicException(sprintf('To use the "%s" Argument Resolver tag with the "expr" option, you need to install the ExpressionLanguage component.', self::class));
        }

        $repository = $this->getManager($options['entity_manager'], $class)->getRepository($class);
        $variables  = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->language->evaluate($expression, $variables);
        } catch (NoResultException | ConversionException $e) {
            return null;
        } catch (SyntaxError $e) {
            throw new LogicException(sprintf('Error parsing expression -- "%s" -- (%s).', $expression, $e->getMessage()), 0, $e);
        }
    }

    /** @return array<string, string> */
    private function getOptions(ArgumentMetadata $argument): array
    {
        /** @var ?Entity $configuration */
        $configuration = method_exists($argument, 'getAttributes') ? $argument->getAttributes(Entity::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null : null;

        if ($configuration === null) {
            return array_merge($this->defaultOptions, [
                'class' => $argument->getType(),
            ]);
        }

        return array_merge($this->defaultOptions, [
            'class' => $configuration->getClass() ?? $argument->getType(),
            'entity_manager' => $configuration->getEntityManager(),
            'expr' => $configuration->getExpr(),
            'mapping' => $configuration->getMapping(),
            'exclude' => $configuration->getExclude(),
            'strip_null' => $configuration->isStripNull(),
            'id' => $configuration->getId(),
            'evict_cache' => $configuration->isEvictCache(),
        ]);
    }
}
