<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WaitForDatabaseCommand
{
    protected static $defaultName = 'doctrine:database:wait';

    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_OPTIONAL,
            'Timeout (in seconds)',
            120
        );

        $this->setDescription('This command tries to call the database and fails in the timeout is reached');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $failure = true;
        $attempts = 0;
        $lastError = 'unknown';

        while($failure) {
            try {
                $this->connection->executeQuery('SELECT 1');
                $failure = false;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $output->writeln("<error>$lastError</error>", OutputInterface::VERBOSITY_VERBOSE);
            }

            if ($failure && $attempts > 120) { // 120 = 2 min

                $output->writeln("\n", OutputInterface::VERBOSITY_VERBOSE);
                $output->writeln("<error>Last error: $lastError</error>", OutputInterface::VERBOSITY_NORMAL);
                return self::FAILURE;
            }

            sleep(1);
            $attempts++;
        }

        return self::SUCCESS;
    }
}
