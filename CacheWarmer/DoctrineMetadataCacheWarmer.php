<?php

namespace Doctrine\Bundle\DoctrineBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

class DoctrineMetadataCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, string $phpArrayFile)
    {
        $this->entityManager = $entityManager;

        parent::__construct($phpArrayFile);
    }

    /**
     * @param string $cacheDir
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        $metadataFactory = new ClassMetadataFactory();
        $metadataFactory->setEntityManager($this->entityManager);
        $metadataFactory->setCacheDriver(new DoctrineProvider($arrayAdapter));
        $metadataFactory->getAllMetadata();

        return true;
    }
}
