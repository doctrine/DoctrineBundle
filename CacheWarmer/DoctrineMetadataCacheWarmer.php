<?php

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use LogicException;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function is_file;

/** @final since 2.11 */
class DoctrineMetadataCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private EntityManagerInterface $entityManager;
    private string $phpArrayFile;

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

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        // cache already warmed up, no needs to do it again
        if (is_file($this->phpArrayFile)) {
            return false;
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        if ($metadataFactory instanceof AbstractClassMetadataFactory && $metadataFactory->getLoadedMetadata()) {
            throw new LogicException('DoctrineMetadataCacheWarmer must load metadata first, check priority of your warmers.');
        }

        $metadataFactory->setCache($arrayAdapter);
        $metadataFactory->getAllMetadata();

        return true;
    }
}
