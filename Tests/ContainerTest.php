<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Version;
use Symfony\Bridge\Doctrine\CacheWarmer\ProxyCacheWarmer;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;

class ContainerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (class_exists(Version::class)) {
            return;
        }

        $this->markTestSkipped('Doctrine ORM is not available.');
    }

    public function testContainer()
    {
        $container = $this->createYamlBundleTestContainer();

        $this->assertInstanceOf(DbalLogger::class, $container->get('doctrine.dbal.logger'));
        $this->assertInstanceOf(DoctrineDataCollector::class, $container->get('data_collector.doctrine'));
        $this->assertInstanceOf(DBALConfiguration::class, $container->get('doctrine.dbal.default_connection.configuration'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.default_connection.event_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('doctrine.dbal.default_connection'));
        $this->assertInstanceOf(Reader::class, $container->get('doctrine.orm.metadata.annotation_reader'));
        $this->assertInstanceOf(Configuration::class, $container->get('doctrine.orm.default_configuration'));
        $this->assertInstanceOf(MappingDriverChain::class, $container->get('doctrine.orm.default_metadata_driver'));
        $this->assertInstanceOf(ArrayCache::class, $container->get('doctrine.orm.default_metadata_cache'));
        $this->assertInstanceOf(ArrayCache::class, $container->get('doctrine.orm.default_query_cache'));
        $this->assertInstanceOf(ArrayCache::class, $container->get('doctrine.orm.default_result_cache'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.default_entity_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('database_connection'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.entity_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.orm.default_entity_manager.event_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.event_manager'));
        $this->assertInstanceOf(ProxyCacheWarmer::class, $container->get('doctrine.orm.proxy_cache_warmer'));
        $this->assertInstanceOf(ManagerRegistry::class, $container->get('doctrine'));
        $this->assertInstanceOf(UniqueEntityValidator::class, $container->get('doctrine.orm.validator.unique'));

        $this->assertSame($container->get('my.platform'), $container->get('doctrine.dbal.default_connection')->getDatabasePlatform());

        $this->assertTrue(Type::hasType('test'));

        $this->assertFalse($container->has('doctrine.dbal.default_connection.events.mysqlsessioninit'));

        if (! interface_exists(PropertyInitializableExtractorInterface::class)) {
            $this->assertInstanceOf(ClassMetadataFactory::class, $container->get('doctrine.orm.default_entity_manager.metadata_factory'));
        }
        $this->assertInstanceOf(DoctrineExtractor::class, $container->get('doctrine.orm.default_entity_manager.property_info_extractor'));

        if (class_exists(DoctrineLoader::class)) {
            $this->assertInstanceOf(DoctrineLoader::class, $container->get('doctrine.orm.default_entity_manager.validator_loader'));
        } else {
            $this->assertFalse($container->has('doctrine.orm.default_entity_manager.validator_loader'));
        }
    }
}
