UPGRADE FROM 2.0 to 2.1
=======================

Commands
--------

 * `Doctrine\Bundle\DoctrineBundle\Command\ImportMappingDoctrineCommand` has been deprecated  

Parameters
----------

Following parameters are deprecated and will no longer be defined nor consumed in future.
Redefine/decorate services where they are used in via DI configuration instead.

* doctrine.class
* doctrine.data_collector.class
* doctrine.dbal.connection.event_manager.class
* doctrine.dbal.connection_factory.class
* doctrine.dbal.configuration.class
* doctrine.dbal.events.mysql_session_init.class
* doctrine.dbal.events.oracle_session_init.class
* doctrine.dbal.logger.chain.class
* doctrine.dbal.logger.class
* doctrine.dbal.logger.profiling.class
* doctrine.orm.cache.apc.class
* doctrine.orm.cache.array.class
* doctrine.orm.cache.memcache.class
* doctrine.orm.cache.memcache_instance.class
* doctrine.orm.cache.memcached.class
* doctrine.orm.cache.memcached_instance.class
* doctrine.orm.cache.redis.class
* doctrine.orm.cache.redis_instance.class
* doctrine.orm.cache.wincache.class
* doctrine.orm.cache.xcache.class
* doctrine.orm.cache.zenddata.class
* doctrine.orm.configuration.class
* doctrine.orm.entity_listener_resolver.class
* doctrine.orm.entity_manager.class
* doctrine.orm.listeners.attach_entity_listeners.class
* doctrine.orm.listeners.resolve_target_entity.class
* doctrine.orm.manager_configurator.class
* doctrine.orm.metadata.annotation.class
* doctrine.orm.metadata.driver_chain.class
* doctrine.orm.metadata.php.class
* doctrine.orm.metadata.staticphp.class
* doctrine.orm.metadata.xml.class
* doctrine.orm.metadata.yml.class
* doctrine.orm.naming_strategy.default.class
* doctrine.orm.naming_strategy.underscore.class
* doctrine.orm.proxy_cache_warmer.class
* doctrine.orm.quote_strategy.ansi.class
* doctrine.orm.quote_strategy.default.class
* doctrine.orm.second_level_cache.cache_configuration.class
* doctrine.orm.second_level_cache.default_cache_factory.class
* doctrine.orm.second_level_cache.default_region.class
* doctrine.orm.second_level_cache.filelock_region.class
* doctrine.orm.second_level_cache.logger_chain.class
* doctrine.orm.second_level_cache.logger_statistics.class
* doctrine.orm.second_level_cache.regions_configuration.class
* doctrine.orm.security.user.provider.class
* doctrine.orm.validator.unique.class
* doctrine.orm.validator_initializer.class
* form.type_guesser.doctrine.class
