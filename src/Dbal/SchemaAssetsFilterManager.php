<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Name;

/**
 * Manages schema filters passed to Connection::setSchemaAssetsFilter()
 */
class SchemaAssetsFilterManager
{
    /** @param callable[] $schemaAssetFilters */
    public function __construct(
        private readonly array $schemaAssetFilters,
    ) {
    }

    /** @param string|AbstractAsset<Name> $assetName */
    public function __invoke(string|AbstractAsset $assetName): bool
    {
        foreach ($this->schemaAssetFilters as $schemaAssetFilter) {
            if ($schemaAssetFilter($assetName) === false) {
                return false;
            }
        }

        return true;
    }
}
