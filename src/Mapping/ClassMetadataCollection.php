<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;

class ClassMetadataCollection
{
    private ?string $path      = null;
    private ?string $namespace = null;

    /** @var ClassMetadata[] */
    private array $metadata;

    /** @param ClassMetadata[] $metadata */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /** @return ClassMetadata[] */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /** @param string $path */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /** @return string|null */
    public function getPath()
    {
        return $this->path;
    }

    /** @param string $namespace */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /** @return string|null */
    public function getNamespace()
    {
        return $this->namespace;
    }
}
