<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\Connection as LegacyConnection;

use function array_keys;

/**
 * Blacklist tables used by well-known Symfony classes.
 *
 * @deprecated Implement your own include/exclude mechanism
 */
class WellKnownSchemaFilterPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $blacklist = [];

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            switch ($definition->getClass()) {
                case DoctrineDbalAdapter::class:
                case PdoAdapter::class:
                    $blacklist[] = $definition->getArguments()[3]['db_table'] ?? 'cache_items';
                    break;

                case PdoSessionHandler::class:
                    $blacklist[] = $definition->getArguments()[1]['db_table'] ?? 'sessions';
                    break;

                case DoctrineDbalStore::class:
                case PdoStore::class:
                    $blacklist[] = $definition->getArguments()[1]['db_table'] ?? 'lock_keys';
                    break;

                case LegacyConnection::class:
                case Connection::class:
                    $blacklist[] = $definition->getArguments()[0]['table_name'] ?? 'messenger_messages';
                    break;
            }
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
