<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\PropertyInfo;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Proxy;
use ReflectionClass;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor as BaseDoctrineExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\VarExporter\LazyObjectInterface;

use function class_exists;
use function ltrim;
use function str_contains;

/**
 * Skips unmapped classes without initializing the entity manager while it is still lazy.
 *
 * @internal
 */
final class DoctrineExtractor extends BaseDoctrineExtractor
{
    /** @param MappingDriver|null $metadataDriver The metadata driver of the entity manager, or null when its metadata factory does not use it to decide which classes are mapped */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventManager $eventManager,
        private readonly MappingDriver|null $metadataDriver = null,
    ) {
        parent::__construct($entityManager);
    }

    /** @param array<string, mixed> $context */
    public function getProperties(string $class, array $context = []): array|null
    {
        return $this->isUnmapped($class) ? null : parent::getProperties($class, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return Type<mixed>|null
     */
    public function getType(string $class, string $property, array $context = []): Type|null
    {
        return $this->isUnmapped($class) ? null : parent::getType($class, $property, $context);
    }

    /** @param array<string, mixed> $context */
    public function isWritable(string $class, string $property, array $context = []): bool|null
    {
        return $this->isUnmapped($class) ? null : parent::isWritable($class, $property, $context);
    }

    private function isUnmapped(string $class): bool
    {
        if ($this->metadataDriver === null || ! $this->isUninitialized()) {
            return false;
        }

        $class = ltrim($class, '\\');

        // Proxy class names and onClassMetadataNotFound listeners can resolve to mapped classes
        if (str_contains($class, '\\' . Proxy::MARKER . '\\') || $this->eventManager->hasListeners(Events::onClassMetadataNotFound)) {
            return false;
        }

        if (! class_exists($class)) {
            return true;
        }

        // Embeddables are transient for metadata drivers, yet they have metadata
        return $this->metadataDriver->isTransient($class) && ! (new ReflectionClass($class))->getAttributes(Embeddable::class);
    }

    private function isUninitialized(): bool
    {
        if ($this->entityManager instanceof LazyObjectInterface) {
            return ! $this->entityManager->isLazyObjectInitialized();
        }

        return (new ReflectionClass($this->entityManager))->isUninitializedLazyObject($this->entityManager);
    }
}
