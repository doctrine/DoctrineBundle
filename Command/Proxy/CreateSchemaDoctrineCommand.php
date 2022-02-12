<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to execute the SQL needed to generate the database schema for
 * a given entity manager.
 */
class CreateSchemaDoctrineCommand extends CreateCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:schema:create')
            ->setDescription('Executes (or dumps) the SQL needed to generate the database schema');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
