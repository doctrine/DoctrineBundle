<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_merge;
use function in_array;
use function sprintf;

/**
 * Database tool allows you to easily drop your configured databases.
 *
 * @final
 */
class DropDatabaseDoctrineCommand extends DoctrineCommand
{
    public const RETURN_CODE_NOT_DROP = 1;

    public const RETURN_CODE_NO_FORCE = 2;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:database:drop')
            ->setDescription('Drops the configured database')
            ->addOption('shard', 's', InputOption::VALUE_REQUIRED, 'The shard connection to use for this command')
            ->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'The connection to use for this command')
            ->addOption('if-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database doesn\'t exist')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Set this parameter to execute this action')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command drops the default connections database:

    <info>php %command.full_name%</info>

The <info>--force</info> parameter has to be used to actually drop the database.

You can also optionally specify the name of a connection to drop the database for:

    <info>php %command.full_name% --connection=default</info>

<error>Be careful: All data in a given database will be lost when executing this command.</error>
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

        $ifExists = $input->getOption('if-exists');

        $params = $connection->getParams();

        if (isset($params['primary'])) {
            $params = $params['primary'];
        }

        if (isset($params['shards'])) {
            $shards = $params['shards'];
            // Default select global
            $params = array_merge($params, $params['global'] ?? []);
            if ($input->getOption('shard')) {
                foreach ($shards as $shard) {
                    if ($shard['id'] === (int) $input->getOption('shard')) {
                        // Select sharded database
                        $params = array_merge($params, $shard);
                        unset($params['id']);
                        break;
                    }
                }
            }
        }

        $name = $params['path'] ?? ($params['dbname'] ?? false);
        if (! $name) {
            throw new InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter and cannot be dropped.");
        }

        unset($params['dbname'], $params['url']);

        if (! $input->getOption('force')) {
            $output->writeln('<error>ATTENTION:</error> This operation should not be executed in a production environment.');
            $output->writeln('');
            $output->writeln(sprintf('<info>Would drop the database <comment>%s</comment> for connection named <comment>%s</comment>.</info>', $name, $connectionName));
            $output->writeln('Please run the operation with --force to execute');
            $output->writeln('<error>All data will be lost!</error>');

            return self::RETURN_CODE_NO_FORCE;
        }

        // Reopen connection without database name set
        // as some vendors do not allow dropping the database connected to.
        $connection->close();
        $connection         = DriverManager::getConnection($params);
        $shouldDropDatabase = ! $ifExists || in_array($name, $connection->getSchemaManager()->listDatabases());

        // Only quote if we don't have a path
        if (! isset($params['path'])) {
            $name = $connection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        try {
            if ($shouldDropDatabase) {
                $connection->getSchemaManager()->dropDatabase($name);
                $output->writeln(sprintf('<info>Dropped database <comment>%s</comment> for connection named <comment>%s</comment></info>', $name, $connectionName));
            } else {
                $output->writeln(sprintf('<info>Database <comment>%s</comment> for connection named <comment>%s</comment> doesn\'t exist. Skipped.</info>', $name, $connectionName));
            }

            return 0;
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Could not drop database <comment>%s</comment> for connection named <comment>%s</comment></error>', $name, $connectionName));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return self::RETURN_CODE_NOT_DROP;
        }
    }
}
