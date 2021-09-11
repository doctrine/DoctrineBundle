<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;

use function assert;
use function class_exists;

/**
 * Provides some helper and convenience methods to configure doctrine commands in the context of bundles
 * and multiple connections/entity managers.
 */
abstract class DoctrineCommandHelper
{
    /**
     * Convenience method to push the helper sets of a given entity manager into the application.
     *
     * @param string $emName
     */
    public static function setApplicationEntityManager(Application $application, $emName)
    {
        $em = $application->getKernel()->getContainer()->get('doctrine')->getManager($emName);
        assert($em instanceof EntityManagerInterface);
        $helperSet = $application->getHelperSet();
        if (class_exists(ConnectionHelper::class)) {
            $helperSet->set(new ConnectionHelper($em->getConnection()), 'db');
        }

        $helperSet->set(new EntityManagerHelper($em), 'em');
    }

    /**
     * Convenience method to push the helper sets of a given connection into the application.
     *
     * @param string $connName
     */
    public static function setApplicationConnection(Application $application, $connName)
    {
        $connection = $application->getKernel()->getContainer()->get('doctrine')->getConnection($connName);
        $helperSet  = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($connection), 'db');
    }
}
