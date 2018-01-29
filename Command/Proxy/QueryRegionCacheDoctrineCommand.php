<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;

/**
 * Command to clear a query cache region.
 */
class QueryRegionCacheDoctrineCommand extends DelegateCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('doctrine:cache:clear-query-region');
    }

    /**
     * {@inheritDoc}
     */
    protected function createCommand()
    {
        return new QueryRegionCommand();
    }

    /**
     * {@inheritDoc}
     */
    protected function getMinimalVersion()
    {
        return '2.5.0-DEV';
    }
}
