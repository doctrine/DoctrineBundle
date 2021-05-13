<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\InfoDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\UpdateSchemaDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\DbalTestKernel;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Bridge\Doctrine\CacheWarmer\ProxyCacheWarmer;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

use function class_exists;
use function interface_exists;

class ContainerTest extends TestCase
{
    public function testContainerWithDbalOnly(): void
    {
        $kernel = new DbalTestKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        $this->assertInstanceOf(
            LoggerChain::class,
            $container->get('doctrine.dbal.logger.chain.default')
        );
        if (class_exists(ImportCommand::class)) {
            self::assertTrue($container->has('doctrine.database_import_command'));
        } else {
            self::assertFalse($container->has('doctrine.database_import_command'));
        }
    }

    public function testContainer(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->createXmlBundleTestContainer();

        $this->assertInstanceOf(DbalLogger::class, $container->get('doctrine.dbal.logger'));
        $this->assertInstanceOf(DoctrineDataCollector::class, $container->get('data_collector.doctrine'));
        $this->assertInstanceOf(DBALConfiguration::class, $container->get('doctrine.dbal.default_connection.configuration'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.default_connection.event_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('doctrine.dbal.default_connection'));
        $this->assertInstanceOf(Reader::class, $container->get('doctrine.orm.metadata.annotation_reader'));
        $this->assertInstanceOf(Configuration::class, $container->get('doctrine.orm.default_configuration'));
        $this->assertInstanceOf(MappingDriverChain::class, $container->get('doctrine.orm.default_metadata_driver'));
        $this->assertInstanceOf(PhpArrayAdapter::class, $container->get('doctrine.orm.default_metadata_cache'));
        $this->assertInstanceOf(DoctrineProvider::class, $container->get('doctrine.orm.default_query_cache'));
        $this->assertInstanceOf(DoctrineProvider::class, $container->get('doctrine.orm.default_result_cache'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.default_entity_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('database_connection'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.entity_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.orm.default_entity_manager.event_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.event_manager'));
        $this->assertInstanceOf(ProxyCacheWarmer::class, $container->get('doctrine.orm.proxy_cache_warmer'));
        $this->assertInstanceOf(ManagerRegistry::class, $container->get('doctrine'));
        $this->assertInstanceOf(UniqueEntityValidator::class, $container->get('doctrine.orm.validator.unique'));
        $this->assertInstanceOf(InfoDoctrineCommand::class, $container->get('doctrine.mapping_info_command'));
        $this->assertInstanceOf(UpdateSchemaDoctrineCommand::class, $container->get('doctrine.schema_update_command'));

        $this->assertSame($container->get('my.platform'), $container->get('doctrine.dbal.default_connection')->getDatabasePlatform());

        $this->assertTrue(Type::hasType('test'));

        $this->assertFalse($container->has('doctrine.dbal.default_connection.events.mysqlsessioninit'));

        $this->assertInstanceOf(DoctrineExtractor::class, $container->get('doctrine.orm.default_entity_manager.property_info_extractor'));

        $this->assertInstanceOf(DoctrineLoader::class, $container->get('doctrine.orm.default_entity_manager.validator_loader'));
    }
}
