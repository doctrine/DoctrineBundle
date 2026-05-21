<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Bundle\DoctrineBundle\Security\Debug\UserSerializationChecker;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set('doctrine.security.debug.user_serialization_checker', UserSerializationChecker::class)
            ->args([
                service('security.firewall.map'),
                service('security.untracked_token_storage'),
                service('doctrine'),
                service('logger'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'security']);
};
