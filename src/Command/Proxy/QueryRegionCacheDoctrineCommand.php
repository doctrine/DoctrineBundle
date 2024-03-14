<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to clear a query cache region.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand instead
 */
class QueryRegionCacheDoctrineCommand extends QueryRegionCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-query-region');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
