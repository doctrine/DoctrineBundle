<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand as DoctrineValidateSchemaCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to run Doctrine ValidateSchema() on the current mappings.
 */
class ValidateSchemaCommand extends DoctrineValidateSchemaCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:validate');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
