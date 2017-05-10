<?php

namespace Doctrine\Bundle\DoctrineBundle\Graphviz\Pass;

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class ImportMetadataPass
{
    public function process(ClassMetadataFactory $factory, $data)
    {
        foreach ($factory->getAllMetadata() as $classMetadata) {
            $class = $classMetadata->getName();

            $data['entities'][$class] = array(
                'associations' => array(),
                'fields'       => array(),
            );

            foreach ($classMetadata->fieldMappings as $name => $config) {
                $data['entities'][$class]['fields'][$name] = $config['type'];
            }

            // associations
            foreach ($classMetadata->getAssociationMappings() as $name => $mapping) {
                $field = $mapping['fieldName'];
                $targetEntity = $mapping['targetEntity'];

                $type = '';
                switch ($mapping['type']) {
                    case ClassMetadataInfo::ONE_TO_MANY:
                        $type = 'one_to_many';
                        break;
                    case ClassMetadataInfo::ONE_TO_ONE:
                        $type = 'one_to_one';
                        break;
                    case ClassMetadataInfo::MANY_TO_ONE:
                        $type = 'many_to_one';
                        break;
                    case ClassMetadataInfo::MANY_TO_MANY:
                        $type = 'many_to_many';
                        break;
                    default:
                        throw new \RuntimeException('Unkown association type '.$mapping['type']);
                }

                $label = $targetEntity;
                if ($type == 'one_to_many' || $type == 'many_to_many') {
                    $label .= '[]';
                }

                $data['entities'][$class]['associations'][$field] = $label;

                if ($mapping['sourceEntity'] === $mapping['targetEntity']) {
                    continue;
                }

                $from = array($mapping['sourceEntity'], $field);
                $to   = array($mapping['targetEntity'], '__class__');

                foreach ($data['relations'] as $relation) {
                    if ($relation['from'] === $from) {
                        continue 2;
                    }
                }

                $data['relations'][] = array(
                    'from' => $from,
                    'to'   => $to,
                    'type' => $type
                );
            }
        }

        return $data;
    }
}
