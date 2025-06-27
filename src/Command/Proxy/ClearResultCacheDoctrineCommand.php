<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to clear the result cache of the various cache drivers.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand instead
 */
class ClearResultCacheDoctrineCommand extends ResultCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-result')
            ->setDescription('Clears result cache for an entity manager');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command');
    }
}
