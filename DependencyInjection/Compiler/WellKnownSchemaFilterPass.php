<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

use function array_keys;
use function method_exists;

/**
 * Blacklist tables used by well-known Symfony classes.
 *
 * @deprecated Implement your own include/exclude mechanism
 *
 * @final since 2.9
 */
class WellKnownSchemaFilterPass implements CompilerPassInterface
{
    /** @return void */
    public function process(ContainerBuilder $container)
    {
        $blacklist = [];

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            if ($definition->getClass() !== PdoSessionHandler::class) {
                continue;
            }

            $table = $definition->getArguments()[1]['db_table'] ?? 'sessions';

            if (! method_exists($definition->getClass(), 'configureSchema')) {
                $blacklist[] = $table;
            }

            break;
        }

        if (! $blacklist) {
            return;
        }

        $definition = $container->getDefinition('doctrine.dbal.well_known_schema_asset_filter');
        $definition->replaceArgument(0, $blacklist);

        foreach (array_keys($container->getParameter('doctrine.connections')) as $name) {
            $definition->addTag('doctrine.dbal.schema_filter', ['connection' => $name]);
        }
    }
}
