<?php

namespace Doctrine\Bundle\DoctrineBundle;

use Closure;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\CacheSchemaSubscriberPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DbalSchemaFilterPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\MiddlewaresPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RemoveLoggingMiddlewarePass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RemoveProfilerControllerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\WellKnownSchemaFilterPass;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Autoloader;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterUidTypePass;
use Symfony\Bridge\Doctrine\DependencyInjection\Security\UserProvider\EntityFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function assert;
use function class_exists;
use function clearstatcache;
use function spl_autoload_unregister;

/** @final since 2.9 */
class DoctrineBundle extends Bundle
{
    private ?Closure $autoloader = null;

    /** @return void */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->has('session.handler')) {
                    return;
                }

                $container->removeDefinition('doctrine.orm.listeners.pdo_session_handler_schema_listener');
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine'), PassConfig::TYPE_BEFORE_OPTIMIZATION);

        if ($container->hasExtension('security')) {
            $security = $container->getExtension('security');

            if ($security instanceof SecurityExtension) {
                $security->addUserProviderFactory(new EntityFactory('entity', 'doctrine.orm.security.user.provider'));
            }
        }

        $container->addCompilerPass(new CacheCompatibilityPass());
        $container->addCompilerPass(new DoctrineValidationPass('orm'));
        $container->addCompilerPass(new EntityListenerPass());
        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
        $container->addCompilerPass(new IdGeneratorPass());
        $container->addCompilerPass(new WellKnownSchemaFilterPass());
        $container->addCompilerPass(new DbalSchemaFilterPass());
        $container->addCompilerPass(new CacheSchemaSubscriberPass(), PassConfig::TYPE_BEFORE_REMOVING, -10);
        $container->addCompilerPass(new RemoveProfilerControllerPass());
        $container->addCompilerPass(new RemoveLoggingMiddlewarePass());
        $container->addCompilerPass(new MiddlewaresPass());

        if (! class_exists(RegisterUidTypePass::class)) {
            return;
        }

        $container->addCompilerPass(new RegisterUidTypePass());
    }

    /** @return void */
    public function boot()
    {
        // Register an autoloader for proxies to avoid issues when unserializing them
        // when the ORM is used.
        if (! $this->container->hasParameter('doctrine.orm.proxy_namespace')) {
            return;
        }

        $namespace      = (string) $this->container->getParameter('doctrine.orm.proxy_namespace');
        $dir            = (string) $this->container->getParameter('doctrine.orm.proxy_dir');
        $proxyGenerator = null;

        if ($this->container->getParameter('doctrine.orm.auto_generate_proxy_classes')) {
            // See https://github.com/symfony/symfony/pull/3419 for usage of references
            /** @psalm-suppress UnsupportedPropertyReferenceUsage */
            $container = &$this->container;

            $proxyGenerator = static function ($proxyDir, $proxyNamespace, $class) use (&$container): void {
                $originalClassName = (new DefaultProxyClassNameResolver())->resolveClassName($class);
                $registry          = $container->get('doctrine');
                assert($registry instanceof Registry);

                foreach ($registry->getManagers() as $em) {
                    assert($em instanceof EntityManagerInterface);
                    if (! $em->getConfiguration()->getAutoGenerateProxyClasses()) {
                        continue;
                    }

                    $metadataFactory = $em->getMetadataFactory();

                    if ($metadataFactory->isTransient($originalClassName)) {
                        continue;
                    }

                    $classMetadata = $metadataFactory->getMetadataFor($originalClassName);

                    $em->getProxyFactory()->generateProxyClasses([$classMetadata]);

                    clearstatcache(true, Autoloader::resolveFile($proxyDir, $proxyNamespace, $class));

                    break;
                }
            };
        }

        $this->autoloader = Autoloader::register($dir, $namespace, $proxyGenerator);
    }

    /** @return void */
    public function shutdown()
    {
        if ($this->autoloader !== null) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }

        // Clear all entity managers to clear references to entities for GC
        if ($this->container->hasParameter('doctrine.entity_managers')) {
            foreach ($this->container->getParameter('doctrine.entity_managers') as $id) {
                if (! $this->container->initialized($id)) {
                    continue;
                }

                $this->container->get($id)->clear();
            }
        }

        // Close all connections to avoid reaching too many connections in the process when booting again later (tests)
        if (! $this->container->hasParameter('doctrine.connections')) {
            return;
        }

        foreach ($this->container->getParameter('doctrine.connections') as $id) {
            if (! $this->container->initialized($id)) {
                continue;
            }

            $this->container->get($id)->close();
        }
    }

    /** @return void */
    public function registerCommands(Application $application)
    {
    }
}
