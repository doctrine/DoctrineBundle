<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Show information about mapped entities
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\InfoCommand instead
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

        $this->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command');
    }
}
