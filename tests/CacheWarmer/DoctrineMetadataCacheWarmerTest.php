<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\CacheWarmer;

use Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\Tests\CacheWarmer\Fixtures\Author;
use Doctrine\Bundle\DoctrineBundle\Tests\CacheWarmer\Fixtures\Book;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

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

    public function testWarmUpDumpsAllMetadataWhenSomeWasLoadedBefore(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $buildDir     = sys_get_temp_dir() . '/' . uniqid('doctrine_metadata_warmer', true);
        $phpArrayFile = $buildDir . '/metadata.php';

        // A required cache warmer or an eager console command can load metadata before this optional warmer runs.
        $entityManager = $this->createEntityManager(new ArrayAdapter());
        $entityManager->getClassMetadata(Author::class);

        $warmer = new DoctrineMetadataCacheWarmer($entityManager, $phpArrayFile);
        $warmer->warmUp($buildDir, $buildDir);

        self::assertFileExists($phpArrayFile);

        // Every class must come from the PHP array file, so nothing is loaded again and stored in the fallback pool.
        $fallbackPool  = new ArrayAdapter();
        $entityManager = $this->createEntityManager(new PhpArrayAdapter($phpArrayFile, $fallbackPool));
        $entityManager->getClassMetadata(Author::class);
        $entityManager->getClassMetadata(Book::class);

        self::assertSame([], $fallbackPool->getValues());
    }

    private function createEntityManager(CacheItemPoolInterface $metadataCache): EntityManagerInterface
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Fixtures']));
        $config->setMetadataCache($metadataCache);
        $config->enableNativeLazyObjects(true);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
    }
}
