<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TestFilter extends SQLFilter
{
    /**
     * Gets the SQL query part to add to a query.
     *
     * {@inheritDoc}
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        return '';
    }
}
