<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;

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
    public static function setApplicationEntityManager(Application $application, $emName)
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
    public static function setApplicationConnection(Application $application, $connName)
    {
        $connection = $application->getKernel()->getContainer()->get('doctrine')->getConnection($connName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($connection), 'db');
    }
}
