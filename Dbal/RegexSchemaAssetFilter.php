<?php

namespace Doctrine\Bundle\DoctrineBundle\Dbal;

use Doctrine\DBAL\Schema\AbstractAsset;

use function preg_match;

class RegexSchemaAssetFilter
{
    /** @var string */
    private $filterExpression;

    public function __construct(string $filterExpression)
    {
        $this->filterExpression = $filterExpression;
    }

    /**
     * @param string|AbstractAsset $assetName
     */
    public function __invoke($assetName): bool
    {
        if ($assetName instanceof AbstractAsset) {
            $assetName = $assetName->getName();
        }

        return (bool) preg_match($this->filterExpression, $assetName);
    }
}
