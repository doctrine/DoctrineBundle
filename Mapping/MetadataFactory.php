<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\MappingException;

/**
 * This class provides methods to access Doctrine entity class metadata for a
 * given bundle, namespace or entity class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MetadataFactory
{
    private $registry;

    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry A ManagerRegistry instance
     */
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
     * @throws \RuntimeException When bundle does not contain mapped entities
     */
    public function getBundleMetadata(BundleInterface $bundle)
    {
        $namespace = $bundle->getNamespace();
        $metadata = $this->getMetadataForNamespace($namespace);
        if (!$metadata->getMetadata()) {
            throw new \RuntimeException(sprintf('Bundle "%s" does not contain any mapped entities.', $bundle->getName()));
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
     * @throws MappingException When class is not valid entity or mapped superclass
     */
    public function getClassMetadata($class, $path = null)
    {
        $metadata = $this->getMetadataForClass($class);
        if (!$metadata->getMetadata()) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($class);
        }

        $this->findNamespaceAndPathForMetadata($metadata);

        return $metadata;
    }

    /**
     * Gets the metadata of all classes of a namespace.
     *
     * @param string $namespace A namespace name
     * @param string $path      The path where the class is stored (if known)
     *
     * @return ClassMetadataCollection A ClassMetadataCollection instance
     * @throws \RuntimeException When namespace not contain mapped entities
     */
    public function getNamespaceMetadata($namespace, $path = null)
    {
        $metadata = $this->getMetadataForNamespace($namespace);
        if (!$metadata->getMetadata()) {
            throw new \RuntimeException(sprintf('Namespace "%s" does not contain any mapped entities.', $namespace));
        }

        $this->findNamespaceAndPathForMetadata($metadata, $path);

        return $metadata;
    }

    /**
     * Find and configure path and namespace for the metadata collection.
     *
     * @param ClassMetadataCollection $metadata
     * @param string|null             $path
     *
     * @throws \RuntimeException When unable to determine the path
     */
    public function findNamespaceAndPathForMetadata(ClassMetadataCollection $metadata, $path = null)
    {
        $all = $metadata->getMetadata();
        if (class_exists($all[0]->name)) {
            $r = new \ReflectionClass($all[0]->name);
            $path = $this->getBasePathForClass($r->getName(), $r->getNamespaceName(), dirname($r->getFilename()));
        } elseif (!$path) {
            throw new \RuntimeException(sprintf('Unable to determine where to save the "%s" class (use the --path option).', $all[0]->name));
        }

        $metadata->setPath($path);
        $metadata->setNamespace(isset($r) ? $r->getNamespaceName() : $all[0]->name);
    }

    /**
     * Get a base path for a class
     *
     * @param string $name      class name
     * @param string $namespace class namespace
     * @param string $path      class path
     *
     * @return string
     * @throws \RuntimeException When base path not found
     */
    private function getBasePathForClass($name, $namespace, $path)
    {
        $namespace = str_replace('\\', '/', $namespace);
        $search = str_replace('\\', '/', $path);
        $destination = str_replace('/' . $namespace, '', $search, $c);

        if ($c != 1) {
            throw new \RuntimeException(sprintf('Can\'t find base path for "%s" (path: "%s", destination: "%s").', $name, $path, $destination));
        }

        return $destination;
    }

    /**
     * @param string $namespace
     *
     * @return ClassMetadataCollection
     */
    private function getMetadataForNamespace($namespace)
    {
        $metadata = array();
        foreach ($this->getAllMetadata() as $m) {
            if (strpos($m->name, $namespace) === 0) {
                $metadata[] = $m;
            }
        }

        return new ClassMetadataCollection($metadata);
    }

    /**
     * @param string $entity
     *
     * @return ClassMetadataCollection
     */
    private function getMetadataForClass($entity)
    {
        foreach ($this->getAllMetadata() as $metadata) {
            if ($metadata->name === $entity) {
                return new ClassMetadataCollection(array($metadata));
            }
        }

        return new ClassMetadataCollection(array());
    }

    /**
     * @return array
     */
    private function getAllMetadata()
    {
        $metadata = array();
        foreach ($this->registry->getManagers() as $em) {
            $class = $this->getClassMetadataFactoryClass();
            /** @var $cmf \Doctrine\ORM\Mapping\ClassMetadataFactory */
            $cmf = new $class();
            $cmf->setEntityManager($em);
            foreach ($cmf->getAllMetadata() as $m) {
                $metadata[] = $m;
            }
        }

        return $metadata;
    }

    /**
     * @return string
     */
    protected function getClassMetadataFactoryClass()
    {
        return 'Doctrine\\ORM\\Mapping\\ClassMetadataFactory';
    }
}
