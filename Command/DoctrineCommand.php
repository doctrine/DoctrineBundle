<?php

namespace Doctrine\Bundle\DoctrineBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\EntityGenerator;
use LogicException;
use Symfony\Component\Console\Command\Command;

/**
 * Base class for Doctrine console commands to extend from.
 *
 * @internal
 */
abstract class DoctrineCommand extends Command
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();

        $this->doctrine = $doctrine;
    }

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
     * @param int|null $shardId
     *
     * @return EntityManager
     */
    protected function getEntityManager($name, $shardId = null)
    {
        $manager = $this->getDoctrine()->getManager($name);

        if ($shardId) {
            if (! $manager->getConnection() instanceof PoolingShardConnection) {
                throw new LogicException(sprintf("Connection of EntityManager '%s' must implement shards configuration.", $name));
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
        return $this->getDoctrine()->getConnection($name);
    }

    /**
     * @return ManagerRegistry
     */
    protected function getDoctrine()
    {
        return $this->doctrine;
    }
}
