<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;

use function assert;
use function trigger_deprecation;

/**
 * Provides some helper and convenience methods to configure doctrine commands in the context of bundles
 * and multiple connections/entity managers.
 *
 * @deprecated since DoctrineBundle 2.7 and will be removed in 3.0
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
        /** @psalm-suppress InvalidArgument ORM < 3 specific */
        $helperSet->set(new EntityManagerHelper($em), 'em');

        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.7',
            'Providing an EntityManager using "%s" is deprecated. Use an instance of "%s" instead.',
            EntityManagerHelper::class,
            EntityManagerProvider::class,
        );
    }
}
