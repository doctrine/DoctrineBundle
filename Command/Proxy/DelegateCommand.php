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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command Delegate.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
abstract class DelegateCommand extends Command
{
    /**
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

    /**
     * @return \Symfony\Component\Console\Command\Command
     */
    abstract protected function createCommand();

    /**
     * @return string
     */
    protected function getMinimalVersion()
    {
        return '2.3.0-DEV';
    }

    /**
     * @return boolean
     */
    private function isVersionCompatible()
    {
        return (version_compare(\Doctrine\ORM\Version::VERSION, $this->getMinimalVersion()) >= 0);
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled()
    {
        return $this->isVersionCompatible();
    }

    /**
     * @param string $entityManagerName
     *
     * @return Command
     */
    protected function wrapCommand($entityManagerName)
    {
        if (!$this->isVersionCompatible()) {
            throw new \RuntimeException(sprintf('"%s" requires doctrine-orm "%s" or newer', $this->getName(), $this->getMinimalVersion()));
        }

        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $entityManagerName);
        $this->command->setApplication($this->getApplication());

        return $this->command;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        if ($this->isVersionCompatible()) {
            $this->command = $this->createCommand();

            $this->setHelp($this->command->getHelp());
            $this->setDefinition($this->command->getDefinition());
            $this->setDescription($this->command->getDescription());
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->wrapCommand($input->getOption('em'))->execute($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->wrapCommand($input->getOption('em'))->interact($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->wrapCommand($input->getOption('em'))->initialize($input, $output);
    }
}
