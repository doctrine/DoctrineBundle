## 1.5.0 (2015-05-28)

Features:

* Added the possibility to configure the quote strategy in the configuration
* Improved the rendering of the query explanation for Postgres
* Added support for tagging entity listeners, without the need to map the listener

Bugfix:

* Fixed the serverVersion configuration for master slave connections
* Fixed the enabling of the profiler to avoid automatically enabling the logger
* Fixed the detection of existing databases when quoted names are used
* Fixed the profiler template when having a manager without any loaded metadata

## 1.4.0 (2015-02-28)

Features:

* Added the ``--if-not-exists`` option in the ``doctrine:database:create`` command
* Added the ``--if-exists`` option in  the ``doctrine:database:drop`` command
* Added the support for the ``url`` DBAL setting
* Added profiling ability for SQL Server

Bugfix:

* Fixed the cache configuration when using ``cache_provider``
* Removed usage of deprecated DI features when using Symfony 2.6+
* Close connections and clear managers on shutdown. This avoids leaking connections in tests.
* Added an exception when the ORM layer is configured but not DBAL

## 1.3.0 (2014-11-28)

Features:

* add support for bundle namespace alias in the mapping compiler pass
* Added support for server_version connection parameter
* Added a way to enable auto_mapping option using multiple entity managers

Bugfix:

* Inlined the profiler picto images instead of getting from FrameworkBundle (where they are gone)
* Remove duplicates in the list of mapped entities in the profile
* Fixed the compatibility with PHP 5.3 (broken in 1.3.0-beta1)

## 1.3.0-beta2 (2014-07-09)

Feature:

* add auto-commit DBAL configuration option
* Use DoctrineCacheBundle to create cache drivers, supporting more configuration
* Added sorting by time in DB panel

Bugfix:

* Fixed the compatibility of the DataCollector with Doctrine 2.4 (bug introduced in 1.3.0-beta1)
* Fixed the exit code of commands on failure
* Fixed the replacement of query parameters in the profiler

## 1.3.0-beta1 (2014-01-26)

Features:

* Added option to configure entity listener resolver service
* add compiler pass for bundles to register mappings
* Added a button to expand/collapse all queries in the profiler
* Added configuration for sharding
* Added support for the new ways to generate proxies in Doctrine Common
* Added configuration for the second-level cache

Bugfix:

* Removed deprecated call
* fix drop and create command for connections with master slave configuration
* Remove usage of deprecated Twig features

## 1.2.0 (2013-03-25)

 * Bumped the requirement to Symfony 2.2
 * Updated the profiler templates for Symfony 2.2

## 1.1.0 (2013-01-12)

 * Added syntax highlighting for queries in teh profiler
 * Added the validation of the mapping in the profiler panel
 * Added return codes for doctrine:database:[create|drop] commands

## 1.0.0 (2012-09-07)

 * Removed the mysql charset hack for 5.3.6+ as PDO has been fixed
 * Implement "keep_slave"/"keepSlave" configuration
 * Added missing Redis cache class mapping.
 * Fixed the XSD schema for the configuration.
 * Added support for schema assets filter configuration
 * integrate naming_strategy into config

## 1.0.0-RC1 (2012-07-04)

 * Add support for targetEntity resolving through the ORM 2.2 listener.
 * Fixed quote database name in doctrine:database:create and doctrine:database:drop commands
 * added a way to use cache services
 * Added a way to configure the default entity repository class
 * Added the support for SQL filters
 * Removed the InfoCommand and proxy the ORM command instead
 * Made the ORM fully optional by avoiding breaking the console
 * Added support for master_slave connections
 * Fixed xml config for proxy parameters
 * Fixes doctrine:generate:entities when called with the --path argument
 * Added missing Memcached cache driver
 * Fix memory leak in Doctrine Autoload Proxy Magic
 * adds lazy-loading event manager, improved listener registration
 * Added a configuration setting for commented types
 * Fixed bug with MetadataFactory having problem when the StaticReflection is used.
 * Added the possibility to explain queries in the profiler
 * Splitted the configuration for the logging and the profiling of the connection

## 1.0.0-beta1 (2011-12-15)

 * [BC break] Changed the namespace from Symfony\Bundle to Doctrine\Bundle
 * Enhance error reporting during mapping validation when nested exceptions occur.
 * Add DoctrineValidationPass to load validation files conditionally
 * Moved the entity provider service to DoctrineBundle
 * Added Stopwatch support in debug mode to integrate in the profiler timeline
 * Removed the IndexedReader
 * Added the implementation of the ManagerRegistry to replace the symfony 2.0 registry
 * Added access to Doctrine's ValidateSchema command from the console. See symfony/symfony#2200.
 * Extracted the bundle from Symfony 2.0
