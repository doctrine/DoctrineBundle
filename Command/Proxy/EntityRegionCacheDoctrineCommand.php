<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand;

/**
 * Command to clear a entity cache region.
 */
class EntityRegionCacheDoctrineCommand extends DelegateCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('doctrine:cache:clear-entity-region');
    }

    /**
     * {@inheritDoc}
     */
    protected function createCommand()
    {
        return new EntityRegionCommand();
    }

    /**
     * {@inheritDoc}
     */
    protected function getMinimalVersion()
    {
        return '2.5.0-DEV';
    }
}
