<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\DBAL\Types\Type;

class ContainerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Doctrine\\ORM\\Version')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }
    }

    public function testContainer()
    {
        $container = $this->createYamlBundleTestContainer();

        $this->assertInstanceOf('Symfony\Bridge\Doctrine\Logger\DbalLogger', $container->get('doctrine.dbal.logger'));
        $this->assertInstanceOf('Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector', $container->get('data_collector.doctrine'));
        $this->assertInstanceOf('Doctrine\DBAL\Configuration', $container->get('doctrine.dbal.default_connection.configuration'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.dbal.default_connection.event_manager'));
        $this->assertInstanceOf('Doctrine\DBAL\Connection', $container->get('doctrine.dbal.default_connection'));
        $this->assertInstanceOf('Doctrine\Common\Annotations\Reader', $container->get('doctrine.orm.metadata.annotation_reader'));
        $this->assertInstanceOf('Doctrine\ORM\Configuration', $container->get('doctrine.orm.default_configuration'));
        $this->assertInstanceOf('Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain', $container->get('doctrine.orm.default_metadata_driver'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_metadata_cache'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_query_cache'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_result_cache'));
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $container->get('doctrine.orm.default_entity_manager'));
        $this->assertInstanceOf('Doctrine\DBAL\Connection', $container->get('database_connection'));
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $container->get('doctrine.orm.entity_manager'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.orm.default_entity_manager.event_manager'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.dbal.event_manager'));
        $this->assertInstanceOf('Symfony\Bridge\Doctrine\CacheWarmer\ProxyCacheWarmer', $container->get('doctrine.orm.proxy_cache_warmer'));
        $this->assertInstanceOf('Doctrine\Common\Persistence\ManagerRegistry', $container->get('doctrine'));
        $this->assertInstanceOf('Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator', $container->get('doctrine.orm.validator.unique'));

        $this->assertSame($container->get('my.platform'), $container->get('doctrine.dbal.default_connection')->getDatabasePlatform());

        $this->assertTrue(Type::hasType('test'));

        if (version_compare(PHP_VERSION, '5.3.6', '<')) {
            $this->assertInstanceOf('Doctrine\DBAL\Event\Listeners\MysqlSessionInit', $container->get('doctrine.dbal.default_connection.events.mysqlsessioninit'));
        } else {
            $this->assertFalse($container->has('doctrine.dbal.default_connection.events.mysqlsessioninit'));
        }

        if (interface_exists('Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface') && class_exists('Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor')) {
            $this->assertInstanceOf('Doctrine\Common\Persistence\Mapping\ClassMetadataFactory', $container->get('doctrine.orm.default_entity_manager.metadata_factory'));
            $this->assertInstanceOf('Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor', $container->get('doctrine.orm.default_entity_manager.property_info_extractor'));
        } else {
            $this->assertFalse($container->has('doctrine.orm.default_entity_manager.metadata_factory'));
            $this->assertFalse($container->has('doctrine.orm.default_entity_manager.property_info_extractor'));
        }
    }
}
