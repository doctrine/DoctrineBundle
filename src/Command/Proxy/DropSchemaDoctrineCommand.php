<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to drop the database schema for a set of classes based on their mappings.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand instead
 */
class DropSchemaDoctrineCommand extends DropCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:drop')
            ->setDescription('Executes (or dumps) the SQL needed to drop the current database schema');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
