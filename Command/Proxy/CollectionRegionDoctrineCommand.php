<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand;

/**
 * Command to clear a collection cache region.
 */
class CollectionRegionDoctrineCommand extends DelegateCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('doctrine:cache:clear-collection-region');
    }

    /**
     * {@inheritDoc}
     */
    protected function createCommand()
    {
        return new CollectionRegionCommand();
    }

    /**
     * {@inheritDoc}
     */
    protected function getMinimalVersion()
    {
        return '2.5.0-DEV';
    }
}
