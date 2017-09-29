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

namespace Doctrine\Bundle\DoctrineBundle;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\RunSqlDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\EntityListenerPass;
use Doctrine\ORM\Proxy\Autoloader;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\DependencyInjection\Security\UserProvider\EntityFactory;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineBundle extends Bundle
{
    private $autoloader;

    /**
     * {@inheritDoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine'), PassConfig::TYPE_BEFORE_OPTIMIZATION);

        if ($container->hasExtension('security')) {
            $container->getExtension('security')->addUserProviderFactory(new EntityFactory('entity', 'doctrine.orm.security.user.provider'));
        }

        $container->addCompilerPass(new DoctrineValidationPass('orm'));
        $container->addCompilerPass(new EntityListenerPass());
    }

    /**
     * {@inheritDoc}
     */
    public function boot()
    {
        // Register an autoloader for proxies to avoid issues when unserializing them
        // when the ORM is used.
        if ($this->container->hasParameter('doctrine.orm.proxy_namespace')) {
            $namespace = $this->container->getParameter('doctrine.orm.proxy_namespace');
            $dir = $this->container->getParameter('doctrine.orm.proxy_dir');
            $proxyGenerator = null;

            if ($this->container->getParameter('doctrine.orm.auto_generate_proxy_classes')) {
                // See https://github.com/symfony/symfony/pull/3419 for usage of references
                $container = &$this->container;

                $proxyGenerator = function ($proxyDir, $proxyNamespace, $class) use (&$container) {
                    $originalClassName = ClassUtils::getRealClass($class);
                    /** @var $registry Registry */
                    $registry = $container->get('doctrine');

                    // Tries to auto-generate the proxy file
                    /** @var $em \Doctrine\ORM\EntityManager */
                    foreach ($registry->getManagers() as $em) {
                        if (!$em->getConfiguration()->getAutoGenerateProxyClasses()) {
                            continue;
                        }

                        $metadataFactory = $em->getMetadataFactory();

                        if ($metadataFactory->isTransient($originalClassName)) {
                            continue;
                        }

                        $classMetadata = $metadataFactory->getMetadataFor($originalClassName);

                        $em->getProxyFactory()->generateProxyClasses(array($classMetadata));

                        clearstatcache(true, Autoloader::resolveFile($proxyDir, $proxyNamespace, $class));

                        break;
                    }
                };
            }

            $this->autoloader = Autoloader::register($dir, $namespace, $proxyGenerator);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shutdown()
    {
        if (null !== $this->autoloader) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }

        // Clear all entity managers to clear references to entities for GC
        if ($this->container->hasParameter('doctrine.entity_managers')) {
            foreach ($this->container->getParameter('doctrine.entity_managers') as $id) {
                if (!method_exists($this->container, 'initialized') || $this->container->initialized($id)) {
                    $this->container->get($id)->clear();
                }
            }
        }

        // Close all connections to avoid reaching too many connections in the process when booting again later (tests)
        if ($this->container->hasParameter('doctrine.connections')) {
            foreach ($this->container->getParameter('doctrine.connections') as $id) {
                if (!method_exists($this->container, 'initialized') || $this->container->initialized($id)) {
                    $this->container->get($id)->close();
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function registerCommands(Application $application)
    {
        // Use the default logic when the ORM is available.
        // This avoids listing all ORM commands by hand.
        if (class_exists('Doctrine\\ORM\\Version')) {
            parent::registerCommands($application);

            return;
        }

        // Register only the DBAL commands if the ORM is not available.
        $application->add(new CreateDatabaseDoctrineCommand());
        $application->add(new DropDatabaseDoctrineCommand());
        $application->add(new RunSqlDoctrineCommand());
        $application->add(new ImportDoctrineCommand());
    }
}
