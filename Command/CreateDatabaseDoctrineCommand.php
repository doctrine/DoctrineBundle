<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function in_array;
use function method_exists;
use function sprintf;

/**
 * Database tool allows you to easily create your configured databases.
 *
 * @final
 */
class CreateDatabaseDoctrineCommand extends DoctrineCommand
{
    protected function configure(): void
    {
        $this
            ->setName('doctrine:database:create')
            ->setDescription('Creates the configured database')
            ->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'The connection to use for this command')
            ->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database already exists')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command creates the default connections database:

    <info>php %command.full_name%</info>

You can also optionally specify the name of a connection to create the database for:

    <info>php %command.full_name% --connection=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionName = $input->getOption('connection');
        if (empty($connectionName)) {
            $connectionName = $this->getDoctrine()->getDefaultConnectionName();
        }

        $connection = $this->getDoctrineConnection($connectionName);

        $ifNotExists = $input->getOption('if-not-exists');

        $params = $connection->getParams();

        if (isset($params['primary'])) {
            $params = $params['primary'];
        }

        $hasPath = isset($params['path']);
        $name    = $hasPath ? $params['path'] : ($params['dbname'] ?? false);
        if (! $name) {
            throw new InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter and cannot be created.");
        }

        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);

        $schemaManager           = method_exists($tmpConnection, 'createSchemaManager')
            ? $tmpConnection->createSchemaManager()
            : $tmpConnection->getSchemaManager();
        $shouldNotCreateDatabase = $ifNotExists && in_array($name, $schemaManager->listDatabases());

        // Only quote if we don't have a path
        if (! $hasPath) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        $error = false;
        try {
            if ($shouldNotCreateDatabase) {
                $output->writeln(sprintf('<info>Database <comment>%s</comment> for connection named <comment>%s</comment> already exists. Skipped.</info>', $name, $connectionName));
            } else {
                $schemaManager->createDatabase($name);
                $output->writeln(sprintf('<info>Created database <comment>%s</comment> for connection named <comment>%s</comment></info>', $name, $connectionName));
            }
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Could not create database <comment>%s</comment> for connection named <comment>%s</comment></error>', $name, $connectionName));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            $error = true;
        }

        $tmpConnection->close();

        return $error ? 1 : 0;
    }
}
