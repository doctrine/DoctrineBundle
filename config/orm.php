<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Bundle\DoctrineBundle\ManagerConfigurator;
use Doctrine\Bundle\DoctrineBundle\Orm\ManagerRegistryAwareEntityManagerProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AnsiQuoteStrategy;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;
use Doctrine\ORM\Tools\Console\Command\Debug\DebugEntityListenersDoctrineCommand;
use Doctrine\ORM\Tools\Console\Command\Debug\DebugEventManagerDoctrineCommand;
use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\SchemaListener\DoctrineDbalCacheAdapterSchemaListener;
use Symfony\Bridge\Doctrine\SchemaListener\LockStoreSchemaListener;
use Symfony\Bridge\Doctrine\SchemaListener\PdoSessionHandlerSchemaListener;
use Symfony\Bridge\Doctrine\SchemaListener\RememberMeTokenProviderDoctrineSchemaListener;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Bridge\Doctrine\Validator\DoctrineInitializer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use const CASE_LOWER;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->alias(EntityManagerInterface::class, 'doctrine.orm.entity_manager')

        ->set('form.type_guesser.doctrine', DoctrineOrmTypeGuesser::class)
            ->tag('form.type_guesser')
            ->args([
                service('doctrine'),
            ])

        ->set('form.type.entity', EntityType::class)
            ->tag('form.type', ['alias' => 'entity'])
            ->args([
                service('doctrine'),
            ])

        ->set('doctrine.orm.configuration', Configuration::class)->abstract()

        ->set('doctrine.orm.entity_manager.abstract', EntityManager::class)
            ->abstract()
            ->lazy()

        ->set('doctrine.orm.container_repository_factory', ContainerRepositoryFactory::class)
            ->args([
                inline_service(ServiceLocator::class)->args([
                    [],
                ]),
            ])

        ->set('doctrine.orm.manager_configurator.abstract', ManagerConfigurator::class)
            ->abstract()
            ->args([
                [],
                [],
            ])

        ->set('doctrine.orm.validator.unique', UniqueEntityValidator::class)
            ->tag('validator.constraint_validator', ['alias' => 'doctrine.orm.validator.unique'])
            ->args([
                service('doctrine'),
            ])

        ->set('doctrine.orm.validator_initializer', DoctrineInitializer::class)
            ->tag('validator.initializer')
            ->args([
                service('doctrine'),
            ])

        ->set('doctrine.orm.security.user.provider', EntityUserProvider::class)
            ->abstract()
            ->args([
                service('doctrine'),
            ])

        ->set('doctrine.orm.listeners.resolve_target_entity', ResolveTargetEntityListener::class)

        ->set('doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener', DoctrineDbalCacheAdapterSchemaListener::class)
            ->args([
                [],
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])

        ->set('doctrine.orm.listeners.doctrine_token_provider_schema_listener', RememberMeTokenProviderDoctrineSchemaListener::class)
            ->args([
                tagged_iterator('security.remember_me_handler'),
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])

        ->set('doctrine.orm.listeners.pdo_session_handler_schema_listener', PdoSessionHandlerSchemaListener::class)
            ->args([
                service('session.handler'),
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])

        ->set('doctrine.orm.listeners.lock_store_schema_listener', LockStoreSchemaListener::class)
            ->args([
                tagged_iterator('lock.store'),
            ])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])

        ->set('doctrine.orm.naming_strategy.default', DefaultNamingStrategy::class)

        ->set('doctrine.orm.naming_strategy.underscore', UnderscoreNamingStrategy::class)

        ->set('doctrine.orm.naming_strategy.underscore_number_aware', UnderscoreNamingStrategy::class)
            ->args([
                CASE_LOWER,
                true,
            ])

        ->set('doctrine.orm.quote_strategy.default', DefaultQuoteStrategy::class)

        ->set('doctrine.orm.quote_strategy.ansi', AnsiQuoteStrategy::class)

        ->set('doctrine.orm.typed_field_mapper.default', DefaultTypedFieldMapper::class)

        ->set('doctrine.ulid_generator', UlidGenerator::class)
            ->args([
                service('ulid.factory')->ignoreOnInvalid(),
            ])
            ->tag('doctrine.id_generator')

        ->set('doctrine.uuid_generator', UuidGenerator::class)
            ->args([
                service('uuid.factory')->ignoreOnInvalid(),
            ])
            ->tag('doctrine.id_generator')

        ->set('doctrine.orm.command.entity_manager_provider', ManagerRegistryAwareEntityManagerProvider::class)
            ->args([
                service('doctrine'),
            ])

        ->set('doctrine.orm.entity_value_resolver', EntityValueResolver::class)
            ->args([
                service('doctrine'),
                service('doctrine.orm.entity_value_resolver.expression_language')->ignoreOnInvalid(),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => 110, 'name' => EntityValueResolver::class])

        ->set('doctrine.orm.entity_value_resolver.expression_language', ExpressionLanguage::class)

        ->set('doctrine.cache_clear_metadata_command', MetadataCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-metadata'])

        ->set('doctrine.cache_clear_query_cache_command', QueryCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-query'])

        ->set('doctrine.cache_clear_result_command', ResultCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-result'])

        ->set('doctrine.cache_collection_region_command', CollectionRegionCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-collection-region'])

        ->set('doctrine.schema_create_command', CreateCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:schema:create'])

        ->set('doctrine.schema_drop_command', DropCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:schema:drop'])

        ->set('doctrine.clear_entity_region_command', EntityRegionCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-entity-region'])

        ->set('doctrine.mapping_info_command', InfoCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:mapping:info'])

        ->set('doctrine.mapping_describe_command', MappingDescribeCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:mapping:describe'])

        ->set('doctrine.clear_query_region_command', QueryRegionCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:cache:clear-query-region'])

        ->set('doctrine.query_dql_command', RunDqlCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:query:dql'])

        ->set('doctrine.schema_update_command', UpdateCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:schema:update'])

        ->set('doctrine.schema_validate_command', ValidateSchemaCommand::class)
            ->args([
                service('doctrine.orm.command.entity_manager_provider'),
            ])
            ->tag('console.command', ['command' => 'doctrine:schema:validate'])

        ->set('doctrine.event_manager_debug_command', DebugEventManagerDoctrineCommand::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('console.command', ['command' => 'doctrine:debug:event-manager'])

        ->set('doctrine.entity_listeners_debug_command', DebugEntityListenersDoctrineCommand::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('console.command', ['command' => 'doctrine:debug:entity-listeners']);
};
