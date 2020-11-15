<?php

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

class DoctrineMetadataCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var string */
    private $phpArrayFile;

    public function __construct(EntityManagerInterface $entityManager, string $phpArrayFile)
    {
        $this->entityManager = $entityManager;
        $this->phpArrayFile  = $phpArrayFile;

        parent::__construct($phpArrayFile);
    }

    /**
     * It must not be optional because it should be called before ProxyCacheWarmer which is not optional.
     */
    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @param string $cacheDir
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        // cache already warmed up, no needs to do it again
        if (is_file($this->phpArrayFile)) {
            return false;
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        if (count($metadataFactory->getLoadedMetadata()) > 0) {
            throw new LogicException('DoctrineMetadataCacheWarmer must load metadata first, check priority of your warmers.');
        }

        $metadataFactory->setCacheDriver(new DoctrineProvider($arrayAdapter));
        $metadataFactory->getAllMetadata();

        return true;
    }
}
