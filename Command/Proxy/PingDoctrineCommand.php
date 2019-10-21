<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\DBAL\Tools\Console\Command\PingCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ping the database to check its accessible.
 */
class PingDoctrineCommand extends PingCommand
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'doctrine:ping';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationConnection($this->getApplication(), $input->getOption('connection'));

        return parent::execute($input, $output);
    }
}
