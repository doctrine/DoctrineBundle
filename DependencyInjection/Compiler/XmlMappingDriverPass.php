<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function count;
use function sprintf;

final class XmlMappingDriverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.entity_managers')) {
            return;
        }

        foreach (array_keys($container->getParameter('doctrine.entity_managers')) as $managerName) {
            $xmlDriverId = sprintf('doctrine.orm.%s_xml_metadata_driver', $managerName);

            if (! $container->hasDefinition($xmlDriverId)) {
                continue;
            }

            $xmlDriverDef = $container->getDefinition($xmlDriverId);
            if ($xmlDriverDef->getClass() === null) {
                continue;
            }

            if ($container->getParameterBag()->resolveValue($xmlDriverDef->getClass()) !== SimplifiedXmlDriver::class) {
                continue;
            }

            $args         = $xmlDriverDef->getArguments();
            $numberOfArgs = count($args);
            if ($numberOfArgs === 0 || $numberOfArgs === 3) {
                continue;
            }

            if ($numberOfArgs < 2) {
                $args[] = SimplifiedXmlDriver::DEFAULT_FILE_EXTENSION;
            }

            // enable validation
            $args[] = true;

            $xmlDriverDef->setArguments($args);
        }
    }
}
