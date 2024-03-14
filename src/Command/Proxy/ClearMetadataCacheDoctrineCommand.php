<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to clear the metadata cache of the various cache drivers.
 *
 * @deprecated use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand instead
 */
class ClearMetadataCacheDoctrineCommand extends MetadataCommand
{
    use OrmProxyCommand;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:cache:clear-metadata')
            ->setDescription('Clears all metadata cache for an entity manager');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }
}
