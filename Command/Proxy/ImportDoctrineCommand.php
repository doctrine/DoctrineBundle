<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

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
        @trigger_error(sprintf('The "%s" (doctrine:database:import) command is deprecated, use a database client instead.', self::class), E_USER_DEPRECATED);

        DoctrineCommandHelper::setApplicationConnection($this->getApplication(), $input->getOption('connection'));

        return parent::execute($input, $output);
    }
}
