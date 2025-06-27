<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to clear a entity cache region.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand instead
 */
class EntityRegionCacheDoctrineCommand extends EntityRegionCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-entity-region');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command');
    }
}
