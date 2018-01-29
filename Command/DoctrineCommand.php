<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\EntityGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Base class for Doctrine console commands to extend from.
 */
abstract class DoctrineCommand extends ContainerAwareCommand
{
    /**
     * get a doctrine entity generator
     *
     * @return EntityGenerator
     */
    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        $entityGenerator->setAnnotationPrefix('ORM\\');

        return $entityGenerator;
    }

    /**
     * Get a doctrine entity manager by symfony name.
     *
     * @param string   $name
     * @param null|int $shardId
     *
     * @return EntityManager
     */
    protected function getEntityManager($name, $shardId = null)
    {
        $manager = $this->getContainer()->get('doctrine')->getManager($name);

        if ($shardId) {
            if (! $manager->getConnection() instanceof PoolingShardConnection) {
                throw new \LogicException(sprintf("Connection of EntityManager '%s' must implement shards configuration.", $name));
            }

            $manager->getConnection()->connect($shardId);
        }

        return $manager;
    }

    /**
     * Get a doctrine dbal connection by symfony name.
     *
     * @param string $name
     *
     * @return Connection
     */
    protected function getDoctrineConnection($name)
    {
        return $this->getContainer()->get('doctrine')->getConnection($name);
    }
}
