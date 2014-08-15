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

namespace Doctrine\Bundle\DoctrineBundle\EventListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandlerSchema;

/**
 * This event listener merges the database session schema with the given one
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class SessionSchemaListener
{
    /**
     * @var DbalSessionHandlerSchema $schema The schema
     */
    private $schema;

    /**
     * Class constructor
     *
     * @param DbalSessionHandlerSchema $schema The schema instance
     */
    public function __construct(DbalSessionHandlerSchema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Callback called when the database schema is generated
     *
     * @param GenerateSchemaEventArgs $args The event args
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $schema = $args->getSchema();

        $this->schema->addToSchema($schema);
    }
}
