<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function trigger_deprecation;

/**
 * Command to clear the metadata cache of the various cache drivers.
 *
 * @deprecated
 */
class ClearMetadataCacheDoctrineCommand extends MetadataCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
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

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.3',
            'The "%s" (doctrine:cache:clear-metadata) is deprecated, metadata cache now uses PHP Array cache which can not be cleared.',
            self::class
        );

        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
