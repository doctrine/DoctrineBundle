<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\CompilerPass;

use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass as BaseMappingPass;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Class for Symfony bundles to configure mappings for model classes not in the
 * automapped folder.
 *
 * TODO: if we need support for the annotation driver too, we probably need an
 * extra compiler pass overwriting buildDriver, as its quite different
 *
 * @author David Buchmann <david@liip.ch>
 */
class RegisterMappingsPass extends BaseMappingPass
{
    /**
     * @param array  $mappings       hashmap of absolute directory paths to namespaces
     * @param string $type           type of mapping, allowed are xml, yml, php
     * @param bool $enabledParameter if specified, the compiler pass only
     *      executes if this parameter exists in the service container.
     */
    public function __construct(array $mappings, $type, $enabledParameter = false)
    {
        switch($type) {
            case 'xml':
                $extension = '.orm.xml';
                $driverClass = 'Doctrine\ORM\Mapping\Driver\XmlDriver';
                break;
            case 'yml':
                $extension = '.yml.xml';
                $driverClass = 'Doctrine\ORM\Mapping\Driver\YamlDriver';
                break;
            case 'php':
                $extension = '.php';
                $driverClass = 'Doctrine\ORM\Mapping\Driver\PHPDriver';
                break;
            default:
                throw new InvalidArgumentException($type);
        }

        parent::__construct(
            $mappings,
            $extension,
            'doctrine.entity_managers',
            $driverClass,
            'doctrine.orm.%s_metadata_driver',
            $enabledParameter
        );

    }
}
