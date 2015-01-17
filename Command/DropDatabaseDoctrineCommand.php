<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DropDatabaseDoctrineCommand extends DoctrineCommand
{
    const RETURN_CODE_NOT_DROP = 1;

    const RETURN_CODE_NO_FORCE = 2;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:database:drop')
            ->setDescription('Drops the configured databases')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command')
            ->addOption('if-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database doesn\'t exists')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter to execute this action')
            ->setHelp(<<<EOT
The <info>doctrine:database:drop</info> command drops the default connections
database:

<info>php app/console doctrine:database:drop</info>

The --force parameter has to be used to actually drop the database.

You can also optionally specify the name of a connection to drop the database
for:

<info>php app/console doctrine:database:drop --connection=default</info>

<error>Be careful: All data in a given database will be lost when executing
this command.</error>
EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getDoctrineConnection($input->getOption('connection'));
        $ifExists = $input->getOption('if-exists');

        $params = $connection->getParams();
        if (isset($params['master'])) {
            $params = $params['master'];
        }

        $name = isset($params['path']) ? $params['path'] : (isset($params['dbname']) ? $params['dbname'] : false);
        if (!$name) {
            throw new \InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter and cannot be dropped.");
        }
        unset($params['dbname']);

        if ($input->getOption('force')) {
            // Reopen connection without database name set
            // as some vendors do not allow dropping the database connected to.
            $connection->close();
            $connection = DriverManager::getConnection($params);

            // Only quote if we don't have a path
            if (!isset($params['path'])) {
                $name = $connection->getDatabasePlatform()->quoteSingleIdentifier($name);
            }

            try {
                $shouldDropDatabase = !$ifExists || in_array($name, $connection->getSchemaManager()->listDatabases());

                if ($shouldDropDatabase) {
                    $connection->getSchemaManager()->dropDatabase($name);
                    $output->writeln(sprintf('<info>Dropped database for connection named <comment>%s</comment></info>', $name));
                } else {
                    $output->writeln(sprintf('<info>Database for connection named <comment>%s</comment> doesn\'t exists. Skipped.</info>', $name));
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>Could not drop database for connection named <comment>%s</comment></error>', $name));
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

                return self::RETURN_CODE_NOT_DROP;
            }
        } else {
            $output->writeln('<error>ATTENTION:</error> This operation should not be executed in a production environment.');
            $output->writeln('');
            $output->writeln(sprintf('<info>Would drop the database named <comment>%s</comment>.</info>', $name));
            $output->writeln('Please run the operation with --force to execute');
            $output->writeln('<error>All data will be lost!</error>');

            return self::RETURN_CODE_NO_FORCE;
        }
    }
}
