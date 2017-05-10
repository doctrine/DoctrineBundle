<?php

namespace Doctrine\Bundle\DoctrineBundle\Graphviz\Pass;

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;

/**
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class InheritancePass
{
    public function process(ClassMetadataFactory $factory, $data)
    {
        foreach ($factory->getAllMetadata() as $classMetadata) {
            $actual = $classMetadata->getName();

            $current = $classMetadata->getReflectionClass();
            while (false !== ($current = $current->getParentClass())) {
                $name = $current->getName();
                    if (isset($data['entities'][$name])) {
                    $data['relations'][] = array(
                        'from' => array($actual, '__class__'),
                        'to'   => array($name, '__class__'),
                        'type' => 'extends'
                    );

                    break;
                }
            }

            // Inherited properties
            foreach ($classMetadata->getReflectionProperties() as $property) {
                $class = $property->getDeclaringClass()->getName();
                $name = $property->getName();

                if ($class !== $actual && isset($data['entities'][$class]['fields'][$name])) {
                    unset($data['entities'][$actual]['fields'][$name]);
                }
            }

        }

        return $data;
    }
}
