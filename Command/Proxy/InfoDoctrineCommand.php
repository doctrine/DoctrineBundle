<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Show information about mapped entities
 */
class InfoDoctrineCommand extends InfoCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        $this
            ->setName('doctrine:mapping:info');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
