<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use LogicException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function is_file;
use function sprintf;

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
            // Another service loaded metadata before this warmer ran. Warm a new factory, so every class reaches the cache.
            $factoryClass    = $this->entityManager->getConfiguration()->getClassMetadataFactoryName();
            $metadataFactory = new $factoryClass();
            if (! $metadataFactory instanceof ClassMetadataFactory) {
                throw new LogicException(sprintf('The metadata factory "%s" must extend "%s".', $factoryClass, ClassMetadataFactory::class));
            }

            $metadataFactory->setEntityManager($this->entityManager);
        }

        $metadataFactory->setCache($arrayAdapter);
        $metadataFactory->getAllMetadata();

        return true;
    }
}
