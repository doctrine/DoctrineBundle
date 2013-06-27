<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\Bundle\DoctrineBundle\Graphviz\DoctrineMetadataGraph;

/**
 * Tool to generate graph from mapping informations.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class GraphvizMappingCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:mapping:graphviz')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists('Alom\Graphviz\Digraph')) {
            $output->writeln('<error>You must add "alom/graphviz" to your composer.json file</error>');

            return 1;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $graph = new DoctrineMetadataGraph($em);

        $output->writeln($graph->render());
    }
}
