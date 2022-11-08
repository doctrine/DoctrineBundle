<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Builder;

class BundleConfigurationBuilder
{
    /** @var array<string, mixed> */
    private array $configuration = [];

    public static function createBuilder(): self
    {
        return new self();
    }

    public static function createBuilderWithBaseValues(): self
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

    /** @param array<string, mixed> $config */
    public function addConnection(array $config): self
    {
        $this->configuration['dbal'] = $config;

        return $this;
    }

    /** @param array<string, mixed> $config */
    public function addEntityManager(array $config): self
    {
        $this->configuration['orm'] = $config;

        return $this;
    }

    /** @param array<string, mixed> $config */
    public function addSecondLevelCache(array $config, string $manager = 'default'): self
    {
        $this->configuration['orm']['entity_managers'][$manager]['second_level_cache'] = $config;

        return $this;
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        return $this->configuration;
    }
}
