<?php

namespace Doctrine\Bundle\DoctrineBundle\Graphviz\Pass;

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;

/**
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class ShortNamePass
{
    public function process(ClassMetadataFactory $factory, $data)
    {
        foreach ($data['entities'] as $name => $entity) {
            $shortName = $this->shortName($name);
            unset($data['entities'][$name]);
            $data['entities'][$shortName] = $this->filterEntity($entity);
        }

        foreach ($data['relations'] as $i => $relation) {
            $data['relations'][$i] = $this->filterRelation($relation);
        }

        return $data;
    }

    protected function filterEntity(array $entity)
    {
        foreach ($entity['associations'] as $i => $association) {
            $name = $association;
            $multiple = false;
            if (preg_match('/\\[\\]$/', $name)) {
                $multiple = true;
                $name = substr($name, 0, -2);
            }
            $shortName = $this->shortName($name);
            if ($multiple) {
                $shortName .= '[]';
            }

            $entity['associations'][$i] = $shortName;
        }

        return $entity;
    }

    protected function filterRelation(array $relation)
    {
        $relation['from'][0] = $this->shortName($relation['from'][0]);
        $relation['to'][0]   = $this->shortName($relation['to'][0]);

        return $relation;
    }

    protected function shortName($name)
    {
        if (preg_match('/^(\w+)\\\\(?:Bundle\\\\)?(\w+)Bundle\\\\.*Entity\\\\(.*)$/', $name, $vars)) {
            if ($vars[1] == $vars[2]) {
                $vars[2] = 'Pha';
            }

            return $vars[2].':'.$vars[3];
        }

        if (preg_match('/^(\w+)\\\\Entity\\\\(\w+)\\\\(\w+)\\\\(.*)$/', $name, $vars)) {
            return $vars[1].$vars[2].$vars[3].':'.$vars[4];
        }

        return $name;
    }
}
