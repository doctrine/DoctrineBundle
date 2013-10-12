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

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;

/**
 * Execute a SQL query and output the results.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class RunSqlDoctrineCommand extends RunSqlCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:query:sql')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command')
            ->setHelp(<<<EOT
The <info>doctrine:query:sql</info> command executes the given SQL query and
outputs the results:

<info>php app/console doctrine:query:sql "SELECT * from user"</info>
EOT
        );
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
