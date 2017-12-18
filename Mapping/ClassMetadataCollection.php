<?php


namespace Doctrine\Bundle\DoctrineBundle\Mapping;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMetadataCollection
{
    private $path;
    private $namespace;
    private $metadata;

    /**
     * Constructor
     *
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }
}
