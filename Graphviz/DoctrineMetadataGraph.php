<?php

namespace Doctrine\Bundle\DoctrineBundle\Graphviz;

use Doctrine\Common\Persistence\ObjectManager;

use Doctrine\Bundle\DoctrineBundle\Graphviz\Pass\ImportMetadataPass;
use Doctrine\Bundle\DoctrineBundle\Graphviz\Pass\InheritancePass;
use Doctrine\Bundle\DoctrineBundle\Graphviz\Pass\ShortNamePass;

use Alom\Graphviz\Digraph;

/**
 * Graphviz graph displaying entity manager metadatas.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class DoctrineMetadataGraph extends Digraph
{
    public function __construct(ObjectManager $manager)
    {
        parent::__construct('G');

        $this->attr('node', array(
            'shape' => 'record'
        ));
        $this->set('rankdir', 'LR');

        $data = $this->createData($manager);

        $clusters = array();

        foreach ($data['entities'] as $class => $entity) {
            $clusterName = $this->getCluster($class);
            if (!isset($clusters[$clusterName])) {
                $clusters[$clusterName] = $this->subgraph('cluster_'.$clusterName)
                    ->set('label', $clusterName)
                    ->set('style', 'filled')
                    ->set('color', '#eeeeee')
                    ->attr('node', array(
                        'style' => 'filled',
                        'color' => '#eecc88',
                        'fillcolor' => '#FCF0AD',
                    ))
                ;
            }

            $label = $this->getEntityLabel($class, $entity);
            $clusters[$clusterName]->node($class, array('label' => $label));
        }

        foreach ($data['relations'] as $association) {
            $attr = array();
            switch ($association['type']) {
                case 'one_to_one':
                case 'one_to_many':
                case 'many_to_one':
                case 'many_to_many':
                    $attr['color'] = '#88888888';
                    $attr['arrowhead'] = 'none';
                    break;
                case 'extends':
            }

            $this->edge(array($association['from'], $association['to']), $attr);
        }
    }

    private function createData(ObjectManager $manager)
    {
        $data = array('entities' => array(), 'relations' => array());
        $passes = array(
            new ImportMetadataPass(),
            new InheritancePass(),
            new ShortNamePass()
        );

        foreach ($passes as $pass) {
            $data = $pass->process($manager->getMetadataFactory(), $data);
        }

        return $data;
    }

    private function getEntityLabel($class, $entity)
    {

        $result = '{{<__class__> '.$class.'|';

        foreach ($entity['associations'] as $name => $val) {
            $result .= '<'.$name.'> '.$name.' : '.$val.'\l|';
        }

        foreach ($entity['fields'] as $name => $val) {
            $result .= $name.' : '.$val.'\l';
        }

        $result .= '}}';

        return $result;
    }

    private function getCluster($entityName)
    {
        $exp = explode(':', $entityName);

        if (count($exp) !== 2) {
            throw new \OutOfBoundsException(sprintf('Unexpected count of ":" in entity name. Expected one ("AcmeDemoBundle:User"), got %s ("%s").', count($exp), $entityName));
        }

        return $exp[0];
    }
}
