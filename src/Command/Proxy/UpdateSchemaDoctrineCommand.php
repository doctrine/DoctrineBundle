<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate the SQL needed to update the database schema to match
 * the current mapping information.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand instead
 */
class UpdateSchemaDoctrineCommand extends UpdateCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:update');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command');
    }
}
