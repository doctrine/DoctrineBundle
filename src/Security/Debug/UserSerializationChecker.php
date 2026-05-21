<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Security\Debug;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Serializable;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

use function method_exists;

class UserSerializationChecker implements EventSubscriberInterface
{
    public function __construct(
        private FirewallMapInterface $firewallMap,
        private TokenStorageInterface $tokenStorage,
        private ManagerRegistry $managerRegistry,
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! $this->firewallMap instanceof FirewallMap) {
            return;
        }

        $firewallConfig = $this->firewallMap->getFirewallConfig($event->getRequest());
        if ($firewallConfig === null || $firewallConfig->isStateless()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (
            $user === null
            || method_exists($user, '__serialize')
            || $user instanceof Serializable
            || $this->managerRegistry->getManagerForClass($user::class)?->getMetadataFactory()->isTransient($user::class) !== false
        ) {
            return;
        }

        $this->logger->warning(
            <<<'WARNING'
                The "{firewallName}" firewall triggered the serialization of a "{userClass}" instance into the session.
                It is recommended you implement its {serialize} and {unserialize} methods
                to only handle data which should disconnect users if they change, like their user identifier and roles.
                WARNING,
            [
                'serialize' => '__serialize()',
                'unserialize' => '__unserialize()',
                'firewallName' => $firewallConfig->getName(),
                'userClass' => $user::class,
            ],
        );
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }
}
