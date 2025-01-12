<?php

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Schema\AbstractAsset;

use function preg_match;

class RegexSchemaAssetFilter
{
    public function __construct(
        private readonly string $filterExpression,
    ) {
    }

    public function __invoke(string|AbstractAsset $assetName): bool
    {
        if ($assetName instanceof AbstractAsset) {
            $assetName = $assetName->getName();
        }

        return (bool) preg_match($this->filterExpression, $assetName);
    }
}
