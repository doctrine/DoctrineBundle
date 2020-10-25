<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Builder;

class BundleConfigurationBuilder
{
    /** @var mixed[] */
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

    public function addBaseConnection(): self
    {
        $this->addConnection([
            'connections' => [
                'default' => ['password' => 'foo'],
            ],
        ]);

        return $this;
    }

    public function addBaseEntityManager(): self
    {
        $this->addEntityManager([
            'default_entity_manager' => 'default',
            'entity_managers' => [
                'default' => [
                    'mappings' => [
                        'YamlBundle' => [],
                    ],
                ],
            ],
        ]);

        return $this;
    }

    public function addBaseSecondLevelCache(): self
    {
        $this->addSecondLevelCache([
            'region_cache_driver' => ['type' => 'pool', 'pool' => 'my_pool'],
            'regions' => [
                'hour_region' => ['lifetime' => 3600],
            ],
        ]);

        return $this;
    }

    public function addConnection($config): self
    {
        $this->configuration['dbal'] = $config;

        return $this;
    }

    public function addEntityManager($config): self
    {
        $this->configuration['orm'] = $config;

        return $this;
    }

    public function addSecondLevelCache($config, $manager = 'default'): self
    {
        $this->configuration['orm']['entity_managers'][$manager]['second_level_cache'] = $config;

        return $this;
    }

    public function build(): array
    {
        return $this->configuration;
    }
}
