<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function trigger_deprecation;

/**
 * Loads an SQL file and executes it.
 *
 * @deprecated Use a database client application instead.
 */
class ImportDoctrineCommand extends ImportCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:database:import')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.2',
            'The "%s" (doctrine:database:import) is deprecated, use a database client instead.',
            self::class
        );

        DoctrineCommandHelper::setApplicationConnection($this->getApplication(), $input->getOption('connection'));

        return parent::execute($input, $output);
    }
}
