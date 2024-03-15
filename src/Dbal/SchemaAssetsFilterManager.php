<?php

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Schema\AbstractAsset;

/**
 * Manages schema filters passed to Connection::setSchemaAssetsFilter()
 */
class SchemaAssetsFilterManager
{
    /** @var callable[] */
    private array $schemaAssetFilters;

    /** @param callable[] $schemaAssetFilters */
    public function __construct(array $schemaAssetFilters)
    {
        $this->schemaAssetFilters = $schemaAssetFilters;
    }

    /** @param string|AbstractAsset $assetName */
    public function __invoke($assetName): bool
    {
        foreach ($this->schemaAssetFilters as $schemaAssetFilter) {
            if ($schemaAssetFilter($assetName) === false) {
                return false;
            }
        }

        return true;
    }
}
