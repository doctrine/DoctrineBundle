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

use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;

/**
 * Command to clear a query cache region.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class QueryRegionCacheDoctrineCommand extends DelegateCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('doctrine:cache:clear-query-region');
    }

    /**
     * {@inheritDoc}
     */
    protected function createCommand()
    {
        return new QueryRegionCommand();
    }

    /**
     * {@inheritDoc}
     */
    protected function getMinimalVersion()
    {
        return '2.5.0-DEV';
    }
}
