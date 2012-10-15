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
namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Component\Console\Input\InputDefinition;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Symfony\Component\Console\Command\Command;

/**
 * Provides some helper and convenience methods to configure doctrine commands in the context of bundles
 * and multiple connections/entity managers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class DoctrineCommandHelper
{
    /**
     * Convenience method to push the helper sets of a given entity manager into the application.
     *
     * @param Application $application
     * @param string      $emName
     */
    static public function setApplicationEntityManager(Application $application, $emName)
    {
        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $application->getKernel()->getContainer()->get('doctrine')->getManager($emName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($em->getConnection()), 'db');
        $helperSet->set(new EntityManagerHelper($em), 'em');
    }

    /**
     * Convenience method to push the helper sets of a given connection into the application.
     *
     * @param Application $application
     * @param string      $connName
     */
    static public function setApplicationConnection(Application $application, $connName)
    {
        $connection = $application->getKernel()->getContainer()->get('doctrine')->getConnection($connName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($connection), 'db');
    }

    /**
     * Convenience method to set default connection or manager into input definition.
     *
     * @param Application     $application
     * @param InputDefinition $definition
     */
    public static function processInputDefinition(InputDefinition $definition, Application $application = null)
    {
        if (null === $application) {
            return $definition;
        }

        $definition = clone $definition;
        $doctrine = $application->getKernel()->getContainer()->get('doctrine');
        $options = $definition->getOptions();

        if (isset($options['connection']) && null === $options['connection']->getDefault()) {
            $options['connection'] = clone $options['connection'];
            $options['connection']->setDefault($doctrine->getDefaultConnectionName());
        } elseif (isset($options['em']) && null === $options['em']->getDefault()) {
            $options['em'] = clone $options['em'];
            $options['em']->setDefault($doctrine->getDefaultManagerName());
        }

        $definition->setOptions($options);

        return $definition;
    }

    /**
     * Convenience method to push the available connections or managers into help of command.
     *
     * @param Application $application
     * @param string      $help
     * @param string      $type        "em" or "connection"
     */
    public static function processCommandHelp($help, $type, Application $application = null)
    {
        if (null === $application) {
            return $help;
        }

        $doctrine = $application->getKernel()->getContainer()->get('doctrine');

        if ('connection' == $type) {
            $help .= "\n\nAvailable connections: ".implode(', ', array_keys($doctrine->getConnectionNames()));
        } elseif ('em' == $type) {
            $help .= "\n\nAvailable entity managers: ".implode(', ', array_keys($doctrine->getManagerNames()));
        }

        return $help;
    }
}
