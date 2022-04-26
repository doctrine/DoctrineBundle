<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Ensure the Doctrine ORM is configured properly for a production environment.
 */
class EnsureProductionSettingsDoctrineCommand extends EnsureProductionSettingsCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:ensure-production-settings');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
