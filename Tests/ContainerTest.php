<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Orm\ManagerRegistryAwareEntityManagerProvider;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Bridge\Doctrine\CacheWarmer\ProxyCacheWarmer;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Bridge\Doctrine\Validator\DoctrineLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

use function interface_exists;

class ContainerTest extends TestCase
{
    public function testContainer(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $container = $this->createXmlBundleTestContainer();

        /** @psalm-suppress UndefinedClass */
        if (interface_exists(Reader::class)) {
            $this->assertInstanceOf(Reader::class, $container->get('doctrine.orm.metadata.annotation_reader'));
        }

        $this->assertInstanceOf(DoctrineDataCollector::class, $container->get('data_collector.doctrine'));
        $this->assertInstanceOf(DBALConfiguration::class, $container->get('doctrine.dbal.default_connection.configuration'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.default_connection.event_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('doctrine.dbal.default_connection'));
        $this->assertInstanceOf(Configuration::class, $container->get('doctrine.orm.default_configuration'));
        $this->assertInstanceOf(MappingDriverChain::class, $container->get('doctrine.orm.default_metadata_driver'));
        $this->assertInstanceOf(PhpArrayAdapter::class, $container->get('doctrine.orm.default_metadata_cache'));
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('doctrine.orm.default_query_cache'));
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('doctrine.orm.default_result_cache'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.default_entity_manager'));
        $this->assertInstanceOf(Connection::class, $container->get('database_connection'));
        $this->assertInstanceOf(EntityManager::class, $container->get('doctrine.orm.entity_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.orm.default_entity_manager.event_manager'));
        $this->assertInstanceOf(EventManager::class, $container->get('doctrine.dbal.event_manager'));
        $this->assertInstanceOf(ProxyCacheWarmer::class, $container->get('doctrine.orm.proxy_cache_warmer'));
        $this->assertInstanceOf(ManagerRegistry::class, $container->get('doctrine'));
        $this->assertInstanceOf(UniqueEntityValidator::class, $container->get('doctrine.orm.validator.unique'));
        $this->assertInstanceOf(InfoCommand::class, $container->get('doctrine.mapping_info_command'));
        $this->assertInstanceOf(UpdateCommand::class, $container->get('doctrine.schema_update_command'));

        $this->assertSame($container->get('my.platform'), $container->get('doctrine.dbal.default_connection')->getDatabasePlatform());

        $this->assertTrue(Type::hasType('test'));

        $this->assertFalse($container->has('doctrine.dbal.default_connection.events.mysqlsessioninit'));

        $this->assertInstanceOf(DoctrineExtractor::class, $container->get('doctrine.orm.default_entity_manager.property_info_extractor'));

        $this->assertInstanceOf(DoctrineLoader::class, $container->get('doctrine.orm.default_entity_manager.validator_loader'));
        $this->assertInstanceOf(ManagerRegistryAwareEntityManagerProvider::class, $container->get('doctrine.orm.command.entity_manager_provider'));
    }
}
