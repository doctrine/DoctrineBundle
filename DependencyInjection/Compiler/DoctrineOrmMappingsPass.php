<?php

namespace Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class for Symfony bundles to configure mappings for model classes not in the
 * auto-mapped folder.
 *
 * NOTE: alias is only supported by Symfony 2.6+ and will be ignored with older versions.
 *
 * @final since 2.9
 */
class DoctrineOrmMappingsPass extends RegisterMappingsPass
{
    /**
     * You should not directly instantiate this class but use one of the
     * factory methods.
     *
     * @param Definition|Reference $driver            Driver DI definition or reference.
     * @param string[]             $namespaces        List of namespaces handled by $driver.
     * @param string[]             $managerParameters Ordered list of container parameters that
     *                                                could hold the manager name.
     *                                                doctrine.default_entity_manager is appended
     *                                                automatically.
     * @param string|false         $enabledParameter  If specified, the compiler pass only executes
     *                                                if this parameter is defined in the service
     *                                                container.
     * @param string[]             $aliasMap          Map of alias to namespace.
     */
    public function __construct($driver, array $namespaces, array $managerParameters, $enabledParameter = false, array $aliasMap = [])
    {
        $managerParameters[] = 'doctrine.default_entity_manager';
        parent::__construct(
            $driver,
            $namespaces,
            $managerParameters,
            'doctrine.orm.%s_metadata_driver',
            $enabledParameter,
            'doctrine.orm.%s_configuration',
            'addEntityNamespace',
            $aliasMap
        );
    }

    /**
     * @param string[]     $namespaces        Hashmap of directory path to namespace.
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createXmlMappingDriver(array $namespaces, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [], bool $enableXsdValidation = false)
    {
        $locator = new Definition(SymfonyFileLocator::class, [$namespaces, '.orm.xml']);
        $driver  = new Definition(XmlDriver::class, [$locator, null, $enableXsdValidation]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /**
     * @param string[]     $namespaces        Hashmap of directory path to namespace
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createYamlMappingDriver(array $namespaces, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [])
    {
        $locator = new Definition(SymfonyFileLocator::class, [$namespaces, '.orm.yml']);
        $driver  = new Definition(YamlDriver::class, [$locator]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /**
     * @param string[]     $namespaces        Hashmap of directory path to namespace
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createPhpMappingDriver(array $namespaces, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [])
    {
        $locator = new Definition(SymfonyFileLocator::class, [$namespaces, '.php']);
        $driver  = new Definition(PHPDriver::class, [$locator]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /**
     * @param string[]     $namespaces        List of namespaces that are handled with annotation mapping
     * @param string[]     $directories       List of directories to look for annotated classes
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createAnnotationMappingDriver(array $namespaces, array $directories, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [])
    {
        $reader = new Reference('annotation_reader');
        $driver = new Definition(AnnotationDriver::class, [$reader, $directories]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /**
     * @param string[]     $namespaces        List of namespaces that are handled with annotation mapping
     * @param string[]     $directories       List of directories to look for annotated classes
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createAttributeMappingDriver(array $namespaces, array $directories, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [])
    {
        $driver = new Definition(AttributeDriver::class, [$directories]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /**
     * @param string[]     $namespaces        List of namespaces that are handled with static php mapping
     * @param string[]     $directories       List of directories to look for static php mapping files
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createStaticPhpMappingDriver(array $namespaces, array $directories, array $managerParameters = [], $enabledParameter = false, array $aliasMap = [])
    {
        $driver = new Definition(StaticPHPDriver::class, [$directories]);

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }
}
