<?php

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

/**
 * Indicates that a controller argument should receive an Entity.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Entity
{
    /** @var string|null */
    private $class;
    /** @var string|null */
    private $entityManager;
    /** @var string|null */
    private $expr;
    /** @var array<string, string> */
    private $mapping;
    /** @var string[] */
    private $exclude;
    /** @var bool */
    private $stripNull;
    /** @var string[]|string|null */
    private $id;
    /** @var bool */
    private $evictCache;

    /**
     * @param array<string, string> $mapping
     * @param string[]              $exclude
     * @param string[]|string|null  $id
     */
    public function __construct(
        ?string $class = null,
        ?string $entityManager = null,
        ?string $expr = null,
        array $mapping = [],
        array $exclude = [],
        bool $stripNull = false,
        $id = null,
        bool $evictCache = false
    ) {
        $this->class         = $class;
        $this->entityManager = $entityManager;
        $this->expr          = $expr;
        $this->mapping       = $mapping;
        $this->exclude       = $exclude;
        $this->stripNull     = $stripNull;
        $this->id            = $id;
        $this->evictCache    = $evictCache;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getEntityManager(): ?string
    {
        return $this->entityManager;
    }

    public function getExpr(): ?string
    {
        return $this->expr;
    }

    /** @return array<string, string> */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /** @return string[] */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function isStripNull(): bool
    {
        return $this->stripNull;
    }

    /** @return string|string[]|null */
    public function getId()
    {
        return $this->id;
    }

    public function isEvictCache(): bool
    {
        return $this->evictCache;
    }
}
