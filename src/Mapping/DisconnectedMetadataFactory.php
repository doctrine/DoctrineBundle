<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

use function dirname;
use function sprintf;
use function str_replace;
use function strpos;

/**
 * This class provides methods to access Doctrine entity class metadata for a
 * given bundle, namespace or entity class, for generation purposes
 */
class DisconnectedMetadataFactory
{
    private ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Gets the metadata of all classes of a bundle.
     *
     * @param BundleInterface $bundle A BundleInterface instance
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     *
     * @throws RuntimeException When bundle does not contain mapped entities.
     */
    public function getBundleMetadata(BundleInterface $bundle)
    {
        $namespace = $bundle->getNamespace();
        $metadata  = $this->getMetadataForNamespace($namespace);
        if (! $metadata->getMetadata()) {
            throw new RuntimeException(sprintf('Bundle "%s" does not contain any mapped entities.', $bundle->getName()));
        }

        $path = $this->getBasePathForClass($bundle->getName(), $bundle->getNamespace(), $bundle->getPath());

        $metadata->setPath($path);
        $metadata->setNamespace($bundle->getNamespace());

        return $metadata;
    }

    /**
     * Gets the metadata of a class.
     *
     * @param string $class A class name
     * @param string $path  The path where the class is stored (if known)
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     *
     * @throws MappingException When class is not valid entity or mapped superclass.
     */
    public function getClassMetadata($class, $path = null)
    {
        $metadata = $this->getMetadataForClass($class);
        if (! $metadata->getMetadata()) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($class);
        }

        $this->findNamespaceAndPathForMetadata($metadata, $path);

        return $metadata;
    }

    /**
     * Gets the metadata of all classes of a namespace.
     *
     * @param string $namespace A namespace name
     * @param string $path      The path where the class is stored (if known)
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     *
     * @throws RuntimeException When namespace not contain mapped entities.
     */
    public function getNamespaceMetadata($namespace, $path = null)
    {
        $metadata = $this->getMetadataForNamespace($namespace);
        if (! $metadata->getMetadata()) {
            throw new RuntimeException(sprintf('Namespace "%s" does not contain any mapped entities.', $namespace));
        }

        $this->findNamespaceAndPathForMetadata($metadata, $path);

        return $metadata;
    }

    /**
     * Find and configure path and namespace for the metadata collection.
     *
     * @param string|null $path
     *
     * @throws RuntimeException When unable to determine the path.
     */
    public function findNamespaceAndPathForMetadata(ClassMetadataCollection $metadata, $path = null)
    {
        $r = new ReflectionClass($metadata->getMetadata()[0]->name);
        $metadata->setPath($this->getBasePathForClass($r->getName(), $r->getNamespaceName(), dirname($r->getFilename())));
        $metadata->setNamespace($r->getNamespaceName());
    }

    /**
     * Get a base path for a class
     *
     * @throws RuntimeException When base path not found.
     */
    private function getBasePathForClass(string $name, string $namespace, string $path): string
    {
        $namespace   = str_replace('\\', '/', $namespace);
        $search      = str_replace('\\', '/', $path);
        $destination = str_replace('/' . $namespace, '', $search, $c);

        if ($c !== 1) {
            throw new RuntimeException(sprintf('Can\'t find base path for "%s" (path: "%s", destination: "%s").', $name, $path, $destination));
        }

        return $destination;
    }

    private function getMetadataForNamespace(string $namespace): ClassMetadataCollection
    {
        $metadata = [];
        foreach ($this->getAllMetadata() as $m) {
            if (strpos($m->name, $namespace) !== 0) {
                continue;
            }

            $metadata[] = $m;
        }

        return new ClassMetadataCollection($metadata);
    }

    private function getMetadataForClass(string $entity): ClassMetadataCollection
    {
        foreach ($this->registry->getManagers() as $em) {
            $cmf = new DisconnectedClassMetadataFactory();
            $cmf->setEntityManager($em);

            if (! $cmf->isTransient($entity)) {
                return new ClassMetadataCollection([$cmf->getMetadataFor($entity)]);
            }
        }

        return new ClassMetadataCollection([]);
    }

    /** @return ClassMetadata[] */
    private function getAllMetadata(): array
    {
        $metadata = [];
        foreach ($this->registry->getManagers() as $em) {
            $cmf = new DisconnectedClassMetadataFactory();
            $cmf->setEntityManager($em);
            foreach ($cmf->getAllMetadata() as $m) {
                $metadata[] = $m;
            }
        }

        return $metadata;
    }
}
