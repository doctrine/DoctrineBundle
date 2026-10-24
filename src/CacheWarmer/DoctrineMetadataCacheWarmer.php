<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function is_file;

/** @internal */
final class DoctrineMetadataCacheWarmer extends BaseCacheWarmer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $phpArrayFile,
    ) {
        parent::__construct($phpArrayFile);
    }

    public function isOptional(): bool
    {
        return true;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, string|null $buildDir = null): bool
    {
        // cache already warmed up, no needs to do it again
        if (is_file($this->phpArrayFile)) {
            return false;
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        if ($metadataFactory->getLoadedMetadata()) {
            throw new LogicException('DoctrineMetadataCacheWarmer must load metadata first, check priority of your warmers.');
        }

        $metadataFactory->setCache($arrayAdapter);
        $metadataFactory->getAllMetadata();

        return true;
    }
}
