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

namespace Doctrine\Bundle\DoctrineBundle\DataCollector;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector as BaseCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DoctrineDataCollector.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class DoctrineDataCollector extends BaseCollector
{
    private $registry;
    private $invalidEntityCount;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;

        parent::__construct($registry);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        $errors = array();
        $entities = array();

        foreach ($this->registry->getManagers() as $name => $em) {
            $entities[$name] = array();
            /** @var $factory \Doctrine\ORM\Mapping\ClassMetadataFactory */
            $factory = $em->getMetadataFactory();
            $validator = new SchemaValidator($em);

            /** @var $class \Doctrine\ORM\Mapping\ClassMetadataInfo */
            foreach ($factory->getLoadedMetadata() as $class) {
                $entities[$name][] = $class->getName();
                $classErrors = $validator->validateClass($class);

                if (!empty($classErrors)) {
                    $errors[$name][$class->getName()] = $classErrors;
                }
            }
        }

        $this->data['entities'] = $entities;
        $this->data['errors'] = $errors;
    }

    public function getEntities()
    {
        return $this->data['entities'];
    }

    public function getMappingErrors()
    {
        return $this->data['errors'];
    }

    public function getInvalidEntityCount()
    {
        if (null === $this->invalidEntityCount) {
            $this->invalidEntityCount = array_sum(array_map('count', $this->data['errors']));
        }

        return $this->invalidEntityCount;
    }
}
