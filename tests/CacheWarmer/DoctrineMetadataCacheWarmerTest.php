<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\CacheWarmer;

use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;

use function interface_exists;
use function sys_get_temp_dir;
use function uniqid;

class DoctrineMetadataCacheWarmerTest extends TestCase
{
    public function testWarmUpDumpsMetadataToThePhpArrayFile(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $buildDir     = sys_get_temp_dir() . '/' . uniqid('doctrine_metadata_warmer', true);
        $phpArrayFile = $buildDir . '/metadata.php';

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getLoadedMetadata')->willReturn([]);
        $metadataFactory->expects(self::once())->method('setCache');
        $metadataFactory->expects(self::once())->method('getAllMetadata')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);

        $warmer = new DoctrineMetadataCacheWarmer($entityManager, $phpArrayFile);

        self::assertTrue($warmer->isOptional());
        self::assertSame([], $warmer->warmUp($buildDir, $buildDir));
        self::assertFileExists($phpArrayFile);
    }
}
