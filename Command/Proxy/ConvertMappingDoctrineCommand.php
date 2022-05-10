<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;
use Doctrine\ORM\Tools\Export\Driver\AbstractExporter;
use Doctrine\ORM\Tools\Export\Driver\XmlExporter;
use Doctrine\ORM\Tools\Export\Driver\YamlExporter;
use Symfony\Component\Console\Input\InputOption;

use function assert;

/**
 * Convert Doctrine ORM metadata mapping information between the various supported
 * formats.
 */
class ConvertMappingDoctrineCommand extends ConvertMappingCommand
{
    use OrmProxyCommand;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('doctrine:mapping:convert');

        if ($this->getDefinition()->hasOption('em')) {
            return;
        }

        $this->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command');
    }

    /**
     * @param string $toType
     * @param string $destPath
     *
     * @return AbstractExporter
     */
    protected function getExporter($toType, $destPath)
    {
        $exporter = parent::getExporter($toType, $destPath);
        assert($exporter instanceof AbstractExporter);
        if ($exporter instanceof XmlExporter) {
            $exporter->setExtension('.orm.xml');
        } elseif ($exporter instanceof YamlExporter) {
            $exporter->setExtension('.orm.yml');
        }

        return $exporter;
    }
}
