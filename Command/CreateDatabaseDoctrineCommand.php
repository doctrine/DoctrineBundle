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

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\DriverManager;

/**
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class CreateDatabaseDoctrineCommand extends DoctrineCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:database:create')
            ->setDescription('Creates the configured databases')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command')
            ->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Only create if the database does not exists')
            ->addOption('drop-and-create', null, InputOption::VALUE_NONE, 'First drop the database and then create it')
            ->setHelp(<<<EOT
The <info>doctrine:database:create</info> command creates the default
connections database:

<info>php app/console doctrine:database:create</info>

You can also optionally specify the name of a connection to create the
database for:

<info>php app/console doctrine:database:create --connection=default</info>
EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($input->getOption('ifNotExists') && $input->getOption('dropAndCreate')){
            $output->writeln("<error>You can't use both <comment>ifNotExists</comment>
            and <comment>dropAndCreate</comment> option</error>");
        }

        $connection = $this->getDoctrineConnection($input->getOption('connection'));

        $params = $connection->getParams();
        if (isset($params['master'])) {
            $params = $params['master'];
        }

        $name = isset($params['path']) ? $params['path'] : (isset($params['dbname']) ? $params['dbname'] : false);
        if (!$name) {
            throw new \InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter and cannot be dropped.");
        }
        unset($params['dbname']);

        $tmpConnection = DriverManager::getConnection($params);

        $dbAlreadyExists = count(array_intersect((array) $name, $tmpConnection->getSchemaManager()->listDatabases()));
        // Only quote if we don't have a path
        if (!isset($params['path'])) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        $error = false;
        try {

            $action = 'createDatabase';
            $action = $input->getOption('dropAndCreate') ? 'dropAndCreateDatabase' : $action;
            $action = $input->getOption('ifNotExists') && $dbAlreadyExists ? '' : $action;
            if ($action) {
                $tmpConnection->getSchemaManager()->{$action}($name);
                $output->writeln(sprintf('<info>Created database <comment>%s</comment> for connection named <comment>%s</comment></info>', $name, $connectionName));
            } else {
                $output->writeln(sprintf('<info>Database <comment>%s</comment> for connection named <comment>%s</comment> already existed</info>', $name, $connectionName));
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not create database for connection named <comment>%s</comment></error>', $name));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            $error = true;
        }

        $tmpConnection->close();

        return $error ? 1 : 0;
    }
}
