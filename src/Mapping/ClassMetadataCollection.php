<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;

class ClassMetadataCollection
{
    private string|null $path      = null;
    private string|null $namespace = null;

    /** @param ClassMetadata[] $metadata */
    public function __construct(
        private readonly array $metadata,
    ) {
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
