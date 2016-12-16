<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Builder;


class BundleConfigurationBuilder
{

    private $configuration;

    public static function createBuilder()
    {
        return new self();
    }

    public static function createBuilderWithBaseValues()
    {
        $builder = new self();
        $builder->addBaseConnection();
        $builder->addBaseEntityManager();

        return $builder;
    }

    public function addBaseConnection()
    {
        $this->addConnection([
            'connections' => [
                'default' => [
                    'password' => 'foo'
                ]
            ]
        ]);

        return $this;
    }

    public function addBaseEntityManager()
    {
        $this->addEntityManager([
            'default_entity_manager' => 'default',
            'entity_managers' => [
                'default' => [
                    'mappings' => [
                        'YamlBundle' => []
                    ]
                ]
            ]
        ]);

        return $this;
    }

    public function addBaseSecondLevelCache()
    {
        $this->addSecondLevelCache([
            'region_cache_driver' => [
                'type' => 'memcache'
            ],
            'regions' => [
                'hour_region' => [
                    'lifetime' => 3600
                ]
            ]
        ]);

        return $this;
    }

    public function addConnection($config)
    {
        $this->configuration['dbal'] = $config;

        return $this;
    }

    public function addEntityManager($config)
    {
        $this->configuration['orm'] = $config;

        return $this;
    }

    public function addSecondLevelCache($config, $manager = 'default')
    {
        $this->configuration['orm']['entity_managers'][$manager]['second_level_cache'] = $config;

        return $this;
    }

    public function build()
    {
        return $this->configuration;
    }
}
