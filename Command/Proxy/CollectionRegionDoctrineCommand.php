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
use Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand;

/**
 * Command to clear a collection cache region.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class CollectionRegionDoctrineCommand extends CollectionRegionCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-collection-region')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
