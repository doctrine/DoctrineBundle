<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Execute a Doctrine DQL query and output the results.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\RunDqlCommand instead
 */
class RunDqlDoctrineCommand extends RunDqlCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:query:dql')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command executes the given DQL query and
outputs the results:

<info>php %command.full_name% "SELECT u FROM UserBundle:User u"</info>

You can also optional specify some additional options like what type of
hydration to use when executing the query:

<info>php %command.full_name% "SELECT u FROM UserBundle:User u" --hydrate=array</info>

Additionally you can specify the first result and maximum amount of results to
show:

<info>php %command.full_name% "SELECT u FROM UserBundle:User u" --first-result=0 --max-result=30</info>
EOT);

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command');
    }
}
